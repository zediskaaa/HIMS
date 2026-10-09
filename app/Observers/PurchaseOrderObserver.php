<?php

namespace App\Observers;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\HimsNotificationWorkflowService;

class PurchaseOrderObserver
{
    public function created(PurchaseOrder $purchaseOrder): void
    {
        $this->notifySupplierIfIssued($purchaseOrder);
    }

    public function updated(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->wasChanged('status')) {
            $this->notifySupplierIfIssued($purchaseOrder);
        }
    }

    private function notifySupplierIfIssued(PurchaseOrder $purchaseOrder): void
    {
        if (! in_array($purchaseOrder->statusEnum(), [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Dispatched], true)) {
            return;
        }

        app(HimsNotificationWorkflowService::class)->purchaseOrderIssued(
            $purchaseOrder,
            ($purchaseOrder->revision_number ?? 1) > 1,
        );
    }
}
