<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\SupplierAccreditationStatus;
use App\Enums\SupplierDocumentStatus;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use App\Models\SupplierAccreditation;
use App\Models\SupplierDocument;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierManagementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): Supplier
    {
        $data['identity_key'] = $this->identityKey($data);
        $this->ensureIdentityIsUnique($data['identity_key']);

        try {
            return DB::transaction(function () use ($data, $actor): Supplier {
                $supplier = Supplier::create([
                    ...$data,
                    'status' => SupplierStatus::Active,
                    'accreditation_status' => SupplierAccreditationStatus::Draft,
                    'created_by' => $actor->id,
                ]);

                $this->audit->log(
                    AuditAction::CreatedSupplier,
                    $actor,
                    'Created a supplier record for accreditation preparation.',
                    $supplier,
                    $supplier->name,
                    newValues: Arr::only($supplier->getAttributes(), ['name', 'trade_name', 'business_structure', 'provides_regulated_health_products', 'status', 'accreditation_status']),
                );

                return $supplier;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateIdentityValidation($data['identity_key']);
        }
    }

    public function update(Supplier $supplier, array $data, User $actor): Supplier
    {
        try {
            return DB::transaction(function () use ($supplier, $data, $actor): Supplier {
                $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
                $data['identity_key'] = $this->identityKey($data + $supplier->only(['tax_number', 'name', 'address', 'billing_address', 'delivery_address']));
                $this->ensureIdentityIsUnique($data['identity_key'], $supplier);

                $supplier->fill($data);
                $changed = array_keys($supplier->getDirty());
                if ($changed === []) {
                    return $supplier;
                }
                $meaningfulChanges = array_values(array_diff($changed, ['identity_key']));
                if ($meaningfulChanges === []) {
                    $supplier->save();

                    return $supplier;
                }

                $materialFields = ['name', 'trade_name', 'business_structure', 'provides_regulated_health_products', 'tax_number', 'address'];
                $materialChanges = array_values(array_intersect($meaningfulChanges, $materialFields));
                if ($supplier->accreditation_status === SupplierAccreditationStatus::PendingReview && $materialChanges !== []) {
                    throw ValidationException::withMessages([
                        'accreditation' => 'Return or reject the pending review before changing legal or compliance identity fields.',
                    ]);
                }

                $auditFields = ['name', 'trade_name', 'business_structure', 'provides_regulated_health_products', 'standard_lead_time_days', 'accreditation_status'];
                $old = Arr::only($supplier->getOriginal(), $auditFields);
                if ($supplier->effectiveAccreditationStatus() === SupplierAccreditationStatus::Approved && $materialChanges !== []) {
                    $supplier->forceFill([
                        'accreditation_status' => SupplierAccreditationStatus::Draft,
                        'accreditation_expires_at' => null,
                        'approved_by' => null,
                    ]);
                }

                $supplier->save();
                $new = [...Arr::only($supplier->getAttributes(), $auditFields), 'changed_fields' => $meaningfulChanges];
                $this->audit->log(AuditAction::UpdatedSupplier, $actor, 'Updated supplier master information.', $supplier, $supplier->name, $old, $new);

                return $supplier;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateIdentityValidation(filled($data['tax_number'] ?? null) ? 'tax:' : null);
        }
    }

    public function submitForReview(Supplier $supplier, User $actor): SupplierAccreditation
    {
        return DB::transaction(function () use ($supplier, $actor): SupplierAccreditation {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($supplier->effectiveAccreditationStatus(), [SupplierAccreditationStatus::Draft, SupplierAccreditationStatus::Rejected, SupplierAccreditationStatus::Expired], true)) {
                throw ValidationException::withMessages(['accreditation' => 'Only a draft, rejected, or expired accreditation can be submitted for review.']);
            }

            $readinessIssues = $supplier->accreditationReviewReadinessIssues();
            if ($readinessIssues !== []) {
                throw ValidationException::withMessages([
                    'accreditation' => 'Complete '.implode(', ', $readinessIssues).' before review.',
                ]);
            }

            $cycle = ((int) $supplier->accreditations()->max('cycle_number')) + 1;
            $accreditation = $supplier->accreditations()->create([
                'cycle_number' => $cycle,
                'status' => SupplierAccreditationStatus::PendingReview,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ]);
            $supplier->update(['accreditation_status' => SupplierAccreditationStatus::PendingReview, 'reviewed_by' => $actor->id, 'last_reviewed_at' => now()]);
            $this->audit->log(AuditAction::SubmittedSupplier, $actor, 'Submitted the supplier for accreditation review.', $supplier, $supplier->name, newValues: ['cycle_number' => $cycle, 'accreditation_status' => SupplierAccreditationStatus::PendingReview->value]);

            return $accreditation;
        });
    }

    public function approve(Supplier $supplier, User $actor, ?string $expiresAt, ?string $notes): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor, $expiresAt, $notes): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            $this->requirePendingReview($supplier);
            $this->requireIndependentDecision($supplier, $actor);

            $invalidRequired = $supplier->documents()
                ->where('is_current', true)
                ->where('required_for_accreditation', true)
                ->where(function ($query): void {
                    $query->where('verification_status', '!=', SupplierDocumentStatus::Verified->value)
                        ->orWhere(fn ($expiry) => $expiry->whereNotNull('expires_at')->whereDate('expires_at', '<', today()));
                })->exists();
            if ($invalidRequired) {
                throw ValidationException::withMessages(['accreditation' => 'Required accreditation documents must be verified and current before approval.']);
            }

            $review = $supplier->accreditations()->where('status', SupplierAccreditationStatus::PendingReview->value)->latest('cycle_number')->lockForUpdate()->firstOrFail();
            $review->update(['status' => SupplierAccreditationStatus::Approved, 'decided_by' => $actor->id, 'decided_at' => now(), 'valid_from' => today(), 'expires_at' => $expiresAt, 'decision_notes' => $notes]);
            $supplier->update(['accreditation_status' => SupplierAccreditationStatus::Approved, 'accreditation_expires_at' => $expiresAt, 'approved_by' => $actor->id, 'last_reviewed_at' => now()]);
            $this->audit->log(AuditAction::ApprovedSupplier, $actor, 'Approved the supplier accreditation.', $supplier, $supplier->name, newValues: ['cycle_number' => $review->cycle_number, 'accreditation_status' => 'approved', 'expires_at' => $expiresAt]);

            return $supplier->fresh();
        });
    }

    public function reject(Supplier $supplier, User $actor, string $notes): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor, $notes): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            $this->requirePendingReview($supplier);
            $this->requireIndependentDecision($supplier, $actor);
            $review = $supplier->accreditations()->where('status', SupplierAccreditationStatus::PendingReview->value)->latest('cycle_number')->lockForUpdate()->firstOrFail();
            $review->update(['status' => SupplierAccreditationStatus::Rejected, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_notes' => $notes]);
            $supplier->update(['accreditation_status' => SupplierAccreditationStatus::Rejected, 'approved_by' => null, 'accreditation_expires_at' => null, 'last_reviewed_at' => now()]);
            $this->audit->log(AuditAction::RejectedSupplier, $actor, 'Rejected the supplier accreditation.', $supplier, $supplier->name, newValues: ['cycle_number' => $review->cycle_number, 'accreditation_status' => 'rejected', 'reason' => $notes]);

            return $supplier->fresh();
        });
    }

    public function suspend(Supplier $supplier, User $actor, string $reason): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor, $reason): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            if ($supplier->status !== SupplierStatus::Active) {
                throw ValidationException::withMessages(['status' => 'Only an active supplier can be suspended.']);
            }
            $supplier->update(['status' => SupplierStatus::Suspended, 'suspension_reason' => $reason]);
            $this->audit->log(AuditAction::SuspendedSupplier, $actor, 'Suspended the supplier from new procurement.', $supplier, $supplier->name, ['status' => 'active'], ['status' => 'suspended', 'reason' => $reason]);

            return $supplier->fresh();
        });
    }

    public function reactivate(Supplier $supplier, User $actor): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($supplier->status, [SupplierStatus::Suspended, SupplierStatus::Inactive], true)) {
                throw ValidationException::withMessages(['status' => 'Only a suspended or inactive supplier can be reactivated.']);
            }
            $oldStatus = $supplier->status->value;
            $supplier->update(['status' => SupplierStatus::Active, 'suspension_reason' => null]);
            $this->audit->log(AuditAction::ReactivatedSupplier, $actor, 'Reactivated the supplier operational record.', $supplier, $supplier->name, ['status' => $oldStatus], ['status' => 'active']);

            return $supplier->fresh();
        });
    }

    public function inactivate(Supplier $supplier, User $actor, string $reason): Supplier
    {
        return DB::transaction(function () use ($supplier, $actor, $reason): Supplier {
            $supplier = Supplier::query()->whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            if ($supplier->status === SupplierStatus::Inactive) {
                throw ValidationException::withMessages(['status' => 'The supplier is already inactive.']);
            }
            $oldStatus = $supplier->status->value;
            $supplier->update(['status' => SupplierStatus::Inactive, 'suspension_reason' => $reason]);
            $this->audit->log(AuditAction::InactivatedSupplier, $actor, 'Inactivated the supplier record for new procurement.', $supplier, $supplier->name, ['status' => $oldStatus], ['status' => 'inactive', 'reason' => $reason]);

            return $supplier->fresh();
        });
    }

    public function verifyDocument(Supplier $supplier, SupplierDocument $document, User $actor, bool $verified, ?string $notes): SupplierDocument
    {
        return DB::transaction(function () use ($supplier, $document, $actor, $verified, $notes): SupplierDocument {
            $document = SupplierDocument::query()->whereKey($document->getKey())->where('supplier_id', $supplier->id)->lockForUpdate()->firstOrFail();
            if (! $document->is_current) {
                throw ValidationException::withMessages(['document' => 'Historical document versions cannot be reviewed again.']);
            }
            if ($document->uploaded_by === $actor->id) {
                throw ValidationException::withMessages(['document' => 'The uploader cannot verify or reject the same document.']);
            }
            if ($document->verification_status !== SupplierDocumentStatus::Pending) {
                throw ValidationException::withMessages(['document' => 'A reviewed document version is immutable; upload a replacement for a new review.']);
            }

            $status = $verified ? SupplierDocumentStatus::Verified : SupplierDocumentStatus::Rejected;
            $document->update(['verification_status' => $status, 'verified_by' => $actor->id, 'verified_at' => now(), 'review_notes' => $notes]);
            $this->audit->log($verified ? AuditAction::VerifiedSupplierDocument : AuditAction::RejectedSupplierDocument, $actor, ($verified ? 'Verified' : 'Rejected').' supplier document evidence.', $supplier, $supplier->name, newValues: ['document_id' => $document->id, 'document_type' => $document->document_type, 'verification_status' => $status->value]);

            return $document->fresh();
        });
    }

    public function recordSupplierProductChange(Supplier $supplier, SupplierProduct $product, User $actor, bool $created): void
    {
        $this->audit->log($created ? AuditAction::AddedSupplierProduct : AuditAction::UpdatedSupplierProduct, $actor, $created ? 'Linked a product to the supplier.' : 'Updated the supplier product relationship.', $supplier, $supplier->name, newValues: ['supplier_product_id' => $product->id, 'item_id' => $product->item_id, 'active' => $product->is_active]);
    }

    private function requirePendingReview(Supplier $supplier): void
    {
        if ($supplier->accreditation_status !== SupplierAccreditationStatus::PendingReview) {
            throw ValidationException::withMessages(['accreditation' => 'The supplier is not pending accreditation review.']);
        }
    }

    private function requireIndependentDecision(Supplier $supplier, User $actor): void
    {
        $participated = $supplier->created_by === $actor->id
            || $supplier->reviewed_by === $actor->id
            || $supplier->documents()->where('is_current', true)->where(function ($query) use ($actor): void {
                $query->where('uploaded_by', $actor->id)->orWhere('verified_by', $actor->id);
            })->exists();

        if ($participated) {
            throw ValidationException::withMessages([
                'accreditation' => 'The accreditation decision must be recorded by an approver who did not create, submit, upload, or verify this supplier review.',
            ]);
        }
    }

    public function canIndependentlyDecide(Supplier $supplier, User $actor): bool
    {
        try {
            $this->requireIndependentDecision($supplier, $actor);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function identityKey(array $data): ?string
    {
        $taxNumber = preg_replace('/[^A-Z0-9]/', '', Str::upper((string) ($data['tax_number'] ?? '')));

        if ($taxNumber !== '') {
            return 'tax:'.$taxNumber;
        }

        $name = Str::squish(Str::lower((string) ($data['name'] ?? '')));
        $address = Str::squish(Str::lower((string) ($data['address'] ?? $data['billing_address'] ?? $data['delivery_address'] ?? '')));

        return $name !== '' && $address !== '' ? 'name-address:'.hash('sha256', $name.'|'.$address) : null;
    }

    private function ensureIdentityIsUnique(?string $identityKey, ?Supplier $except = null): void
    {
        if ($identityKey === null) {
            return;
        }

        $exists = Supplier::where('identity_key', $identityKey)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($exists) {
            throw $this->duplicateIdentityValidation($identityKey);
        }
    }

    private function duplicateIdentityValidation(?string $identityKey): ValidationException
    {
        $field = str_starts_with((string) $identityKey, 'tax:') ? 'tax_number' : 'name';

        return ValidationException::withMessages([$field => 'A supplier with this legal identity already exists.']);
    }
}
