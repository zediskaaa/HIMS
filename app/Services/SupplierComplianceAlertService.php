<?php

namespace App\Services;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierDocumentStatus;
use App\Models\Supplier;
use App\Models\SupplierComplianceAlert;
use Carbon\CarbonInterface;

class SupplierComplianceAlertService
{
    private const WARNING_DAYS = 30;

    /**
     * Synchronize persistent in-app alerts from actual validity dates.
     *
     * @return array{active: int, resolved: int}
     */
    public function sweep(): array
    {
        $today = today();
        $warningDate = $today->copy()->addDays(self::WARNING_DAYS);
        $activeKeys = [];

        Supplier::query()
            ->with([
                'documents' => fn ($query) => $query->where('is_current', true)->where('verification_status', SupplierDocumentStatus::Verified->value)->whereNotNull('expires_at')->whereDate('expires_at', '<=', $warningDate),
                'contracts' => fn ($query) => $query->where('status', 'active')->whereNotNull('ends_at')->whereDate('ends_at', '<=', $warningDate),
            ])
            ->where(function ($query) use ($warningDate): void {
                $query->whereHas('documents', fn ($documents) => $documents->where('is_current', true)->where('verification_status', SupplierDocumentStatus::Verified->value)->whereNotNull('expires_at')->whereDate('expires_at', '<=', $warningDate))
                    ->orWhereHas('contracts', fn ($contracts) => $contracts->where('status', 'active')->whereNotNull('ends_at')->whereDate('ends_at', '<=', $warningDate))
                    ->orWhere(fn ($accreditation) => $accreditation
                        ->where('accreditation_status', SupplierAccreditationStatus::Approved->value)
                        ->whereNotNull('accreditation_expires_at')
                        ->whereDate('accreditation_expires_at', '<=', $warningDate));
            })
            ->each(function (Supplier $supplier) use (&$activeKeys, $today): void {
                if ($supplier->accreditation_status === SupplierAccreditationStatus::Approved
                    && $supplier->accreditation_expires_at !== null) {
                    $activeKeys[] = $this->record(
                        sourceKey: 'supplier:'.$supplier->id.':accreditation',
                        supplier: $supplier,
                        dueDate: $supplier->accreditation_expires_at,
                        type: $supplier->accreditation_expires_at->lt($today) ? 'accreditation_expired' : 'accreditation_expiring',
                        message: 'Supplier accreditation '.($supplier->accreditation_expires_at->lt($today) ? 'expired' : 'expires').' on '.$supplier->accreditation_expires_at->toDateString().'.',
                        severity: $supplier->accreditation_expires_at->lt($today) ? AlertSeverity::Critical : AlertSeverity::Warning,
                    );
                }

                foreach ($supplier->documents as $document) {
                    $expired = $document->expires_at->lt($today);
                    $activeKeys[] = $this->record(
                        sourceKey: 'document:'.$document->id,
                        supplier: $supplier,
                        dueDate: $document->expires_at,
                        type: $expired ? 'document_expired' : 'document_expiring',
                        message: $document->document_type.' '.($expired ? 'expired' : 'expires').' on '.$document->expires_at->toDateString().'.',
                        severity: $expired && $document->blocks_procurement_when_invalid ? AlertSeverity::Critical : AlertSeverity::Warning,
                        documentId: $document->id,
                    );
                }

                foreach ($supplier->contracts as $contract) {
                    $expired = $contract->ends_at->lt($today);
                    $activeKeys[] = $this->record(
                        sourceKey: 'contract:'.$contract->id,
                        supplier: $supplier,
                        dueDate: $contract->ends_at,
                        type: $expired ? 'contract_expired' : 'contract_expiring',
                        message: 'Contract '.$contract->contract_number.' '.($expired ? 'expired' : 'expires').' on '.$contract->ends_at->toDateString().'.',
                        severity: AlertSeverity::Warning,
                        contractId: $contract->id,
                    );
                }
            });

        $resolved = SupplierComplianceAlert::active()
            ->when($activeKeys !== [], fn ($query) => $query->whereNotIn('source_key', $activeKeys))
            ->update(['status' => AlertStatus::Resolved->value, 'resolved_at' => now()]);

        return ['active' => count($activeKeys), 'resolved' => $resolved];
    }

    private function record(
        string $sourceKey,
        Supplier $supplier,
        CarbonInterface $dueDate,
        string $type,
        string $message,
        AlertSeverity $severity,
        ?int $documentId = null,
        ?int $contractId = null,
    ): string {
        $alert = SupplierComplianceAlert::firstOrNew(['source_key' => $sourceKey]);
        $alert->fill([
            'supplier_id' => $supplier->id,
            'supplier_document_id' => $documentId,
            'supplier_contract_id' => $contractId,
            'type' => $type,
            'severity' => $severity,
            'status' => AlertStatus::Open,
            'due_date' => $dueDate,
            'message' => $message,
            'resolved_at' => null,
        ]);
        $alert->first_detected_at ??= now();
        $alert->save();

        return $sourceKey;
    }
}
