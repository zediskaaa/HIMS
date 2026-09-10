<?php

namespace App\Enums;

enum AuditAction: string
{
    case CreatedUser = 'created_user';
    case UpdatedUser = 'updated_user';
    case DeletedUser = 'deleted_user';
    case LoggedIn = 'logged_in';
    case LoggedOut = 'logged_out';
    case ChangedPassword = 'changed_password';
    case TemporarilyLockedUser = 'temporarily_locked_user';
    case UnlockedUser = 'unlocked_user';
    case CreatedSupplier = 'created_supplier';
    case UpdatedSupplier = 'updated_supplier';
    case SubmittedSupplier = 'submitted_supplier';
    case ApprovedSupplier = 'approved_supplier';
    case RejectedSupplier = 'rejected_supplier';
    case SuspendedSupplier = 'suspended_supplier';
    case InactivatedSupplier = 'inactivated_supplier';
    case ReactivatedSupplier = 'reactivated_supplier';
    case UploadedSupplierDocument = 'uploaded_supplier_document';
    case VerifiedSupplierDocument = 'verified_supplier_document';
    case RejectedSupplierDocument = 'rejected_supplier_document';
    case AddedSupplierProduct = 'added_supplier_product';
    case UpdatedSupplierProduct = 'updated_supplier_product';
    case AddedSupplierPrice = 'added_supplier_price';
    case AddedSupplierContract = 'added_supplier_contract';
    case UpdatedSupplierContract = 'updated_supplier_contract';

    public function label(): string
    {
        return match ($this) {
            self::CreatedUser => 'Created User',
            self::UpdatedUser => 'Updated User',
            self::DeletedUser => 'Deleted User',
            self::LoggedIn => 'Logged In',
            self::LoggedOut => 'Logged Out',
            self::ChangedPassword => 'Changed Password',
            self::TemporarilyLockedUser => 'Temporarily Locked User',
            self::UnlockedUser => 'Unlocked User',
            self::CreatedSupplier => 'Created Supplier',
            self::UpdatedSupplier => 'Updated Supplier',
            self::SubmittedSupplier => 'Submitted Supplier',
            self::ApprovedSupplier => 'Approved Supplier',
            self::RejectedSupplier => 'Rejected Supplier',
            self::SuspendedSupplier => 'Suspended Supplier',
            self::InactivatedSupplier => 'Inactivated Supplier',
            self::ReactivatedSupplier => 'Reactivated Supplier',
            self::UploadedSupplierDocument => 'Uploaded Supplier Document',
            self::VerifiedSupplierDocument => 'Verified Supplier Document',
            self::RejectedSupplierDocument => 'Rejected Supplier Document',
            self::AddedSupplierProduct => 'Added Supplier Product',
            self::UpdatedSupplierProduct => 'Updated Supplier Product',
            self::AddedSupplierPrice => 'Added Supplier Price',
            self::AddedSupplierContract => 'Added Supplier Contract',
            self::UpdatedSupplierContract => 'Updated Supplier Contract',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action) => [$action->value => $action->label()])
            ->all();
    }
}
