<?php

namespace App\Services\Logistics;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Models\LogisticsDocument;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTrackingService
{
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const MAX_FILE_BYTES = 15728640; // 15 MB

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ChainOfCustodyService $custodyService,
    ) {}

    /**
     * Upload and register a new logistics document.
     *
     * @param  array<string, mixed>  $data
     */
    public function uploadDocument(array $data, UploadedFile $file, User $uploader): LogisticsDocument
    {
        $this->validateFile($file);

        $path = $file->store('logistics_documents', 'local');
        if (! $path) {
            throw new DomainException('Failed to store document file to disk.');
        }

        try {
            $checksum = hash_file('sha256', $file->getRealPath());
            $docType = $data['document_type'] instanceof DocumentType
                ? $data['document_type']
                : (DocumentType::tryFrom($data['document_type']) ?? DocumentType::Other);

            $prefix = $docType->numberPrefix();
            $trackingNumber = "DOC-{$prefix}-" . now()->format('Ym') . '-' . Str::upper(Str::random(5));

            $retentionClass = $data['retention_class'] ?? $this->determineRetentionClass($docType);
            $retentionUntil = LogisticsDocument::defaultRetentionDate($retentionClass);

            return DB::transaction(function () use ($data, $file, $path, $checksum, $docType, $trackingNumber, $retentionClass, $retentionUntil, $uploader): LogisticsDocument {
                $document = LogisticsDocument::create([
                    'tracking_number' => $trackingNumber,
                    'document_type' => $docType,
                    'title' => $data['title'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'purchase_order_id' => $data['purchase_order_id'] ?? null,
                    'goods_receipt_note_id' => $data['goods_receipt_note_id'] ?? null,
                    'inspection_acceptance_report_id' => $data['inspection_acceptance_report_id'] ?? null,
                    'material_requisition_id' => $data['material_requisition_id'] ?? null,
                    'status' => 'submitted',
                    'file_path' => $path,
                    'file_name' => basename($path),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'file_size_bytes' => $file->getSize(),
                    'disk' => 'local',
                    'sha256_checksum' => $checksum,
                    'issued_at' => ! empty($data['issued_at']) ? $data['issued_at'] : null,
                    'received_at' => ! empty($data['received_at']) ? $data['received_at'] : now(),
                    'retention_class' => $retentionClass,
                    'retention_until' => $retentionUntil,
                    'uploaded_by_id' => $uploader->id,
                    'version_number' => 1,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Record custody entry
                $this->custodyService->recordTransfer($document, [
                    'event_type' => 'dock_receiving',
                    'releasing_party_name' => $document->supplier?->name ?? 'External Issuer / Carrier',
                    'receiving_user_id' => $uploader->id,
                    'receiving_party_name' => $uploader->name . ' (Records Custodian)',
                    'origin_location' => 'External Issuance',
                    'destination_location' => 'HIMS Secure Logistics Repository',
                    'package_condition' => 'good_order',
                    'verification_method' => 'credential_auth',
                    'notes' => "Uploaded document {$trackingNumber} ({$document->title}). SHA-256: {$checksum}.",
                ], $uploader);

                $this->auditLogger->record(
                    AuditAction::UploadedLogisticsDocument,
                    actor: $uploader,
                    target: $document,
                    description: "Uploaded logistics document {$trackingNumber} ({$document->title}).",
                    newValues: [
                        'tracking_number' => $trackingNumber,
                        'document_type' => $docType->value,
                        'checksum' => $checksum,
                        'file_size_bytes' => $document->file_size_bytes,
                    ],
                );

                return $document;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    /**
     * Verify or reject a logistics document.
     */
    public function verifyDocument(LogisticsDocument $document, string $decision, ?string $notes, User $verifier): LogisticsDocument
    {
        return DB::transaction(function () use ($document, $decision, $notes, $verifier): LogisticsDocument {
            $locked = LogisticsDocument::lockForUpdate()->findOrFail($document->id);

            if ($locked->isArchived()) {
                throw new DomainException("Archived document #{$locked->tracking_number} cannot be modified.");
            }

            if (! in_array($decision, ['verified', 'rejected', 'correction_required'], true)) {
                throw new DomainException("Invalid verification decision: {$decision}.");
            }

            if ($decision !== 'verified' && empty($notes)) {
                throw ValidationException::withMessages([
                    'verification_notes' => ['Verification notes are required when rejecting or requesting correction.'],
                ]);
            }

            $locked->status = $decision;
            $locked->verified_by_id = $verifier->id;
            $locked->verified_at = now();
            $locked->verification_notes = $notes;
            $locked->save();

            $this->auditLogger->record(
                AuditAction::VerifiedLogisticsDocument,
                actor: $verifier,
                target: $locked,
                description: "Document {$locked->tracking_number} verification marked as '{$decision}'. Notes: {$notes}",
                newValues: [
                    'status' => $decision,
                    'verified_at' => $locked->verified_at,
                    'notes' => $notes,
                ],
            );

            return $locked;
        });
    }

    /**
     * Upload a corrected/revised version of an existing document without overwriting historical records.
     *
     * @param  array<string, mixed>  $data
     */
    public function reviseDocument(LogisticsDocument $document, array $data, UploadedFile $newFile, User $uploader): LogisticsDocument
    {
        return DB::transaction(function () use ($document, $data, $newFile, $uploader): LogisticsDocument {
            $locked = LogisticsDocument::lockForUpdate()->findOrFail($document->id);

            if ($locked->superseded_by_id !== null) {
                throw new DomainException("Document #{$locked->tracking_number} has already been superseded by #{$locked->supersededBy?->tracking_number}.");
            }

            $this->validateFile($newFile);
            $path = $newFile->store('logistics_documents', 'local');
            $checksum = hash_file('sha256', $newFile->getRealPath());

            $newVersionNumber = (int) $locked->version_number + 1;
            $trackingNumber = "DOC-{$locked->document_type->numberPrefix()}-" . now()->format('Ym') . '-' . Str::upper(Str::random(5));

            $newDoc = LogisticsDocument::create([
                'tracking_number' => $trackingNumber,
                'document_type' => $locked->document_type,
                'title' => $data['title'] ?? $locked->title,
                'reference_number' => $data['reference_number'] ?? $locked->reference_number,
                'supplier_id' => $locked->supplier_id,
                'purchase_order_id' => $locked->purchase_order_id,
                'goods_receipt_note_id' => $locked->goods_receipt_note_id,
                'inspection_acceptance_report_id' => $locked->inspection_acceptance_report_id,
                'material_requisition_id' => $locked->material_requisition_id,
                'status' => 'submitted',
                'file_path' => $path,
                'file_name' => basename($path),
                'original_name' => $newFile->getClientOriginalName(),
                'mime_type' => $newFile->getMimeType() ?? 'application/octet-stream',
                'file_size_bytes' => $newFile->getSize(),
                'disk' => 'local',
                'sha256_checksum' => $checksum,
                'issued_at' => $data['issued_at'] ?? $locked->issued_at,
                'received_at' => now(),
                'retention_class' => $locked->retention_class,
                'retention_until' => $locked->retention_until,
                'uploaded_by_id' => $uploader->id,
                'version_number' => $newVersionNumber,
                'replaces_document_id' => $locked->id,
                'revision_reason' => $data['revision_reason'] ?? 'Superseding correction.',
                'notes' => $data['notes'] ?? $locked->notes,
            ]);

            // Mark old document as superseded
            $locked->superseded_by_id = $newDoc->id;
            $locked->status = 'archived';
            $locked->save();

            $this->auditLogger->record(
                AuditAction::RevisedLogisticsDocument,
                actor: $uploader,
                target: $newDoc,
                description: "Created revision v{$newVersionNumber} ({$trackingNumber}) superseding #{$locked->tracking_number}.",
                newValues: [
                    'tracking_number' => $trackingNumber,
                    'version_number' => $newVersionNumber,
                    'replaces_document_id' => $locked->id,
                    'revision_reason' => $newDoc->revision_reason,
                ],
            );

            return $newDoc;
        });
    }

    /**
     * Download authorized document file via protected stream.
     */
    public function downloadDocument(LogisticsDocument $document): StreamedResponse
    {
        if (empty($document->file_path) || ! Storage::disk($document->disk)->exists($document->file_path)) {
            abort(404, 'The requested document file could not be found on storage.');
        }

        return Storage::disk($document->disk)->download(
            $document->file_path,
            $document->original_name ?? $document->file_name
        );
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded document exceeds the maximum allowable file size of 15MB.'],
            ]);
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => ['Invalid document format. Only PDF, JPG, PNG, and WebP files are permitted.'],
            ]);
        }
    }

    private function determineRetentionClass(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::DeliveryReceipt, DocumentType::PackingList, DocumentType::Waybill, DocumentType::BillOfLading => 'operational_2yr',
            DocumentType::Invoice, DocumentType::SalesInvoice => 'tax_invoice_5yr',
            DocumentType::IarReport, DocumentType::PurchaseOrder, DocumentType::Contract, DocumentType::RisSlip => 'financial_10yr',
            default => 'financial_10yr',
        };
    }
}
