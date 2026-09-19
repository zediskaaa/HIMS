<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\BarcodeAlias;
use App\Models\CycleCountDoc;
use App\Models\GoodsReceiptNote;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\LogisticsDocument;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GlobalSearchService
{
    /**
     * Default number of items returned per category in the dropdown suggestions.
     */
    public const DEFAULT_LIMIT_PER_CATEGORY = 4;

    /**
     * Search across permitted HIMS entities for the authenticated user.
     *
     * @return array{
     *     query: string,
     *     total_results: int,
     *     categories: array<int, array{
     *         key: string,
     *         label: string,
     *         icon: string,
     *         total: int,
     *         has_more: bool,
     *         view_all_url: ?string,
     *         items: array<int, array{
     *             id: string,
     *             type: string,
     *             title: string,
     *             subtitle: string,
     *             badge: ?string,
     *             badge_variant: string,
     *             url: string,
     *             icon: string
     *         }>
     *     }>
     * }
     */
    public function search(User $user, string $query, int $limit = self::DEFAULT_LIMIT_PER_CATEGORY): array
    {
        $term = trim($query);

        if (mb_strlen($term) < 2) {
            return [
                'query' => $term,
                'total_results' => 0,
                'categories' => [],
            ];
        }

        $categories = [];
        $totalResults = 0;

        // 1. Inventory Items (Items, SKU, Barcodes)
        if ($user->can(Permission::ViewInventory->value)) {
            $category = $this->searchInventoryItems($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 2. Suppliers
        if ($user->can(Permission::ViewSuppliers->value)) {
            $category = $this->searchSuppliers($user, $term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 3. Purchase Orders
        if ($user->can(Permission::ViewProcurement->value)) {
            $category = $this->searchPurchaseOrders($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 4. Requisitions & Purchase Requests
        $requisitionsCategory = $this->searchRequisitions($user, $term, $limit);
        if ($requisitionsCategory !== null && ! empty($requisitionsCategory['items'])) {
            $categories[] = $requisitionsCategory;
            $totalResults += count($requisitionsCategory['items']);
        }

        // 5. Stock Movements
        if ($user->can(Permission::ViewInventory->value)) {
            $category = $this->searchStockMovements($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 6. Shipments
        if ($user->can(Permission::ViewLogisticsRecords->value) || $user->can(Permission::ViewLogisticsSensitiveData->value)) {
            $category = $this->searchShipments($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 7. Documents (Logistics Documents)
        if ($user->can(Permission::ViewLogisticsSensitiveData->value)) {
            $category = $this->searchDocuments($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 8. Goods Receipt Notes (GRN)
        if ($user->can(Permission::ViewInventory->value)) {
            $category = $this->searchGoodsReceiptNotes($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 9. Inspection Acceptance Reports (IAR)
        if ($user->can(Permission::ViewLogisticsSensitiveData->value)) {
            $category = $this->searchInspectionReports($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 10. Stock Transfers
        if ($user->can(Permission::ViewInventory->value)) {
            $category = $this->searchStockTransfers($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 11. Cycle Counts
        if ($user->can(Permission::PerformCycleCount->value)) {
            $category = $this->searchCycleCounts($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 12. Warehouse Tasks
        if ($user->can(Permission::ViewWarehouseTasks->value)) {
            $category = $this->searchWarehouseTasks($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 13. Storage Locations
        if ($user->can(Permission::ViewInventory->value)) {
            $category = $this->searchStorageLocations($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        // 14. Users / Employees (Protected: Administrator / Super Administrator with ManageUsers only)
        if ($user->can(Permission::ManageUsers->value)) {
            $category = $this->searchUsers($term, $limit);
            if (! empty($category['items'])) {
                $categories[] = $category;
                $totalResults += count($category['items']);
            }
        }

        return [
            'query' => $term,
            'total_results' => $totalResults,
            'categories' => $categories,
        ];
    }

    /**
     * Search Inventory Items by Name, SKU, Barcode, Generic Name, Brand Name, or Barcode Alias.
     */
    protected function searchInventoryItems(string $term, int $limit): array
    {
        $query = InventoryItem::query()
            ->with(['category'])
            ->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode_value', 'like', "%{$term}%")
                    ->orWhere('gtin', 'like', "%{$term}%")
                    ->orWhere('generic_name', 'like', "%{$term}%")
                    ->orWhere('brand_name', 'like', "%{$term}%")
                    ->orWhereIn('id', function ($sub) use ($term): void {
                        $sub->select('target_id')
                            ->from('barcode_aliases')
                            ->where('target_type', 'inventory_item')
                            ->where('is_active', true)
                            ->where('code', 'like', "%{$term}%");
                    });
            });

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (InventoryItem $item): array {
            $qoh = (int) $item->quantity_on_hand;
            $reorder = (int) $item->reorder_level;
            $condition = match (true) {
                $qoh <= 0 => ['Out of Stock', 'danger'],
                $qoh <= $reorder => ['Low Stock', 'warning'],
                default => ['In Stock', 'success'],
            };

            $subtitleParts = [];
            $subtitleParts[] = "SKU: {$item->sku}";
            if ($item->barcode_value) {
                $subtitleParts[] = "Barcode: {$item->barcode_value}";
            }
            $subtitleParts[] = "Qty: {$qoh} ".($item->unit ?: 'units');
            if ($item->category?->name) {
                $subtitleParts[] = $item->category->name;
            }

            return [
                'id' => "item-{$item->id}",
                'type' => 'inventory_item',
                'title' => $item->name,
                'subtitle' => implode(' · ', $subtitleParts),
                'badge' => $condition[0],
                'badge_variant' => $condition[1],
                'url' => route('inventory.items', ['search' => $item->sku]),
                'icon' => 'cube',
            ];
        })->all();

        return [
            'key' => 'inventory_items',
            'label' => 'Inventory Items',
            'icon' => 'cube',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.items', ['search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Suppliers by Name, Trade Name, Contact Person, and (if permitted) Tax Number / Email.
     */
    protected function searchSuppliers(User $user, string $term, int $limit): array
    {
        $canSensitive = $user->can(Permission::ViewSupplierSensitiveData->value);

        $query = Supplier::query()
            ->where(function ($q) use ($term, $canSensitive): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('trade_name', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'like', "%{$term}%");

                if ($canSensitive) {
                    $q->orWhere('tax_number', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                }
            });

        $total = (clone $query)->count();
        $records = $query->orderBy('name')->take($limit)->get();

        $items = $records->map(function (Supplier $supplier): array {
            $subtitleParts = [];
            if ($supplier->trade_name && $supplier->trade_name !== $supplier->name) {
                $subtitleParts[] = $supplier->trade_name;
            }
            if ($supplier->contact_person) {
                $subtitleParts[] = "Contact: {$supplier->contact_person}";
            }
            if ($supplier->phone) {
                $subtitleParts[] = $supplier->phone;
            }

            $statusLabel = $supplier->status?->label() ?? ucfirst($supplier->status?->value ?? 'active');
            $variant = match ($supplier->status?->value) {
                'active' => 'success',
                'suspended' => 'danger',
                'inactive' => 'neutral',
                default => 'warning',
            };

            return [
                'id' => "supplier-{$supplier->id}",
                'type' => 'supplier',
                'title' => $supplier->name,
                'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Registered Supplier',
                'badge' => $statusLabel,
                'badge_variant' => $variant,
                'url' => route('inventory.suppliers.show', $supplier),
                'icon' => 'building-office-2',
            ];
        })->all();

        return [
            'key' => 'suppliers',
            'label' => 'Suppliers',
            'icon' => 'building-office-2',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.suppliers', ['search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Purchase Orders by PO Number, ORS/BURS Number, or Supplier Name.
     */
    protected function searchPurchaseOrders(string $term, int $limit): array
    {
        $query = PurchaseOrder::query()
            ->with('supplier')
            ->where(function ($q) use ($term): void {
                $q->where('po_number', 'like', "%{$term}%")
                    ->orWhere('ors_burs_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$term}%"));
            });

        $total = (clone $query)->count();
        $records = $query->latest('requested_at')->latest('id')->take($limit)->get();

        $items = $records->map(function (PurchaseOrder $po): array {
            $subtitleParts = [];
            if ($po->supplier?->name) {
                $subtitleParts[] = $po->supplier->name;
            }
            if ($po->total_amount) {
                $subtitleParts[] = '₱'.number_format((float) $po->total_amount, 2);
            }
            if ($po->delivery_date) {
                $subtitleParts[] = 'Due: '.$po->delivery_date->format('M d, Y');
            }

            $statusText = ucfirst(str_replace('_', ' ', $po->status ?? 'open'));
            $variant = match ($po->status) {
                'received', 'fulfilled' => 'success',
                'cancelled', 'rejected' => 'danger',
                'dispatched', 'in_transit' => 'primary',
                default => 'neutral',
            };

            return [
                'id' => "po-{$po->id}",
                'type' => 'purchase_order',
                'title' => "PO #{$po->po_number}",
                'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Purchase Order',
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.purchases', ['po_search' => $po->po_number]),
                'icon' => 'clipboard-document-check',
            ];
        })->all();

        return [
            'key' => 'purchase_orders',
            'label' => 'Purchase Orders',
            'icon' => 'clipboard-document-check',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.purchases', ['po_search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Requisitions (Material Requisitions & Purchase Requests).
     */
    protected function searchRequisitions(User $user, string $term, int $limit): ?array
    {
        $canViewInventory = $user->can(Permission::ViewInventory->value);
        $canViewProcurement = $user->can(Permission::ViewProcurement->value);

        if (! $canViewInventory && ! $canViewProcurement) {
            return null;
        }

        $items = [];
        $total = 0;

        // Material Store Requisitions
        if ($canViewInventory) {
            $reqQuery = MaterialRequisition::query()
                ->where(function ($q) use ($term): void {
                    $q->where('requisition_number', 'like', "%{$term}%")
                        ->orWhere('department', 'like', "%{$term}%")
                        ->orWhere('justification', 'like', "%{$term}%");
                });

            $total += (clone $reqQuery)->count();
            $reqs = $reqQuery->latest('id')->take($limit)->get();

            foreach ($reqs as $req) {
                $subtitleParts = [];
                if ($req->department) {
                    $subtitleParts[] = "Dept: {$req->department}";
                }
                if ($req->urgency) {
                    $subtitleParts[] = ucfirst($req->urgency);
                }

                $statusText = ucfirst(str_replace('_', ' ', $req->status ?? 'pending'));
                $variant = match ($req->status) {
                    'issued', 'approved' => 'success',
                    'rejected', 'cancelled' => 'danger',
                    default => 'neutral',
                };

                $items[] = [
                    'id' => "req-{$req->id}",
                    'type' => 'requisition',
                    'title' => "Requisition #{$req->requisition_number}",
                    'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Material Requisition',
                    'badge' => $statusText,
                    'badge_variant' => $variant,
                    'url' => route('inventory.requisitions.show', $req),
                    'icon' => 'document-text',
                ];
            }
        }

        // Purchase Requests
        if ($canViewProcurement && count($items) < $limit) {
            $prLimit = $limit - count($items);
            $prQuery = PurchaseRequest::query()
                ->where(function ($q) use ($term): void {
                    $q->where('pr_number', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%");
                });

            $total += (clone $prQuery)->count();
            $prs = $prQuery->latest('id')->take($prLimit)->get();

            foreach ($prs as $pr) {
                $subtitleParts = [];
                if ($pr->title) {
                    $subtitleParts[] = Str::limit($pr->title, 40);
                }
                if ($pr->total_estimated_amount) {
                    $subtitleParts[] = 'Est: ₱'.number_format((float) $pr->total_estimated_amount, 2);
                }

                $statusText = ucfirst(str_replace('_', ' ', $pr->status?->value ?? $pr->status ?? 'pending'));
                $items[] = [
                    'id' => "pr-{$pr->id}",
                    'type' => 'purchase_request',
                    'title' => "PR #{$pr->pr_number}",
                    'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Purchase Request',
                    'badge' => $statusText,
                    'badge_variant' => 'neutral',
                    'url' => route('inventory.purchases', ['po_search' => $pr->pr_number]),
                    'icon' => 'document-text',
                ];
            }
        }

        if (empty($items)) {
            return null;
        }

        return [
            'key' => 'requisitions',
            'label' => 'Requisitions & Requests',
            'icon' => 'document-text',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => $canViewInventory
                ? route('inventory.requisitions.index', ['search' => $term])
                : route('inventory.purchases', ['po_search' => $term]),
            'items' => array_slice($items, 0, $limit),
        ];
    }

    /**
     * Search Stock Movements by Remarks, Item Name/SKU, Batch, or Cryptographic Hash.
     */
    protected function searchStockMovements(string $term, int $limit): array
    {
        $query = StockMovement::query()
            ->with(['item', 'batch'])
            ->where(function ($q) use ($term): void {
                $q->where('remarks', 'like', "%{$term}%")
                    ->orWhere('hash', 'like', "%{$term}%")
                    ->orWhereHas('item', function ($iq) use ($term): void {
                        $iq->where('name', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "%{$term}%");
                    })
                    ->orWhereHas('batch', function ($bq) use ($term): void {
                        $bq->where('batch_number', 'like', "%{$term}%");
                    });
            });

        $total = (clone $query)->count();
        $records = $query->latest('moved_at')->latest('id')->take($limit)->get();

        $items = $records->map(function (StockMovement $movement): array {
            $movementLabel = $movement->movement_type?->label() ?? 'Movement';
            $itemName = $movement->item?->name ?? 'Item #'.$movement->item_id;

            $subtitleParts = [];
            $subtitleParts[] = "Qty: {$movement->quantity}";
            if ($movement->batch?->batch_number) {
                $subtitleParts[] = "Batch: {$movement->batch->batch_number}";
            }
            if ($movement->moved_at) {
                $subtitleParts[] = $movement->moved_at->format('M d, Y H:i');
            }
            if ($movement->remarks) {
                $subtitleParts[] = Str::limit($movement->remarks, 30);
            }

            return [
                'id' => "movement-{$movement->id}",
                'type' => 'stock_movement',
                'title' => "{$movementLabel}: {$itemName}",
                'subtitle' => implode(' · ', $subtitleParts),
                'badge' => $movementLabel,
                'badge_variant' => 'neutral',
                'url' => route('inventory.stock-movements', ['search' => $movement->item?->name ?? $term]),
                'icon' => 'arrows-right-left',
            ];
        })->all();

        return [
            'key' => 'stock_movements',
            'label' => 'Stock Movements',
            'icon' => 'arrows-right-left',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.stock-movements', ['search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Shipments by Shipment Number, Tracking Number, Waybill Number, Carrier, or SSCC.
     */
    protected function searchShipments(string $term, int $limit): array
    {
        $query = Shipment::query()
            ->with('supplier')
            ->where(function ($q) use ($term): void {
                $q->where('shipment_number', 'like', "%{$term}%")
                    ->orWhere('tracking_number', 'like', "%{$term}%")
                    ->orWhere('waybill_number', 'like', "%{$term}%")
                    ->orWhere('carrier_name', 'like', "%{$term}%")
                    ->orWhere('sscc', 'like', "%{$term}%");
            });

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (Shipment $shipment): array {
            $subtitleParts = [];
            if ($shipment->carrier_name) {
                $subtitleParts[] = $shipment->carrier_name;
            }
            if ($shipment->tracking_number) {
                $subtitleParts[] = "Track: {$shipment->tracking_number}";
            }
            if ($shipment->supplier?->name) {
                $subtitleParts[] = $shipment->supplier->name;
            }

            $statusText = ucfirst(str_replace('_', ' ', $shipment->status ?? 'in_transit'));
            $variant = match ($shipment->status) {
                'delivered' => 'success',
                'delayed', 'exception' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "shipment-{$shipment->id}",
                'type' => 'shipment',
                'title' => "Shipment #{$shipment->shipment_number}",
                'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Inbound Shipment',
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.logistics.shipments', ['search' => $shipment->shipment_number]),
                'icon' => 'truck',
            ];
        })->all();

        return [
            'key' => 'shipments',
            'label' => 'Shipments',
            'icon' => 'truck',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.logistics.shipments', ['search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Logistics Documents by Tracking Number, Reference Number, Title, or Filename.
     */
    protected function searchDocuments(string $term, int $limit): array
    {
        $query = LogisticsDocument::query()
            ->where(function ($q) use ($term): void {
                $q->where('tracking_number', 'like', "%{$term}%")
                    ->orWhere('reference_number', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('original_name', 'like', "%{$term}%");
            });

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (LogisticsDocument $doc): array {
            $subtitleParts = [];
            $subtitleParts[] = "Tracking: {$doc->tracking_number}";
            if ($doc->reference_number) {
                $subtitleParts[] = "Ref: {$doc->reference_number}";
            }
            if ($doc->document_type) {
                $subtitleParts[] = $doc->document_type->label() ?? $doc->document_type->value;
            }

            $statusText = ucfirst($doc->status ?? 'uploaded');
            $variant = match ($doc->status) {
                'verified' => 'success',
                'rejected' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "doc-{$doc->id}",
                'type' => 'document',
                'title' => $doc->title ?: "Document #{$doc->tracking_number}",
                'subtitle' => implode(' · ', $subtitleParts),
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.logistics.documents', ['search' => $doc->tracking_number]),
                'icon' => 'document-duplicate',
            ];
        })->all();

        return [
            'key' => 'documents',
            'label' => 'Documents & Tracking',
            'icon' => 'document-duplicate',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.logistics.documents', ['search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Goods Receipt Notes (GRN) by GRN Number, DR Number, or Invoice Number.
     */
    protected function searchGoodsReceiptNotes(string $term, int $limit): array
    {
        $query = GoodsReceiptNote::query()
            ->with('supplier')
            ->where(function ($q) use ($term): void {
                $q->where('grn_number', 'like', "%{$term}%")
                    ->orWhere('dr_number', 'like', "%{$term}%")
                    ->orWhere('sales_invoice_number', 'like', "%{$term}%");
            });

        $total = (clone $query)->count();
        $records = $query->latest('received_at')->latest('id')->take($limit)->get();

        $items = $records->map(function (GoodsReceiptNote $grn): array {
            $subtitleParts = [];
            if ($grn->dr_number) {
                $subtitleParts[] = "DR: {$grn->dr_number}";
            }
            if ($grn->supplier?->name) {
                $subtitleParts[] = $grn->supplier->name;
            }
            if ($grn->received_at) {
                $subtitleParts[] = $grn->received_at->format('M d, Y');
            }

            $statusText = ucfirst(str_replace('_', ' ', $grn->status ?? 'received'));
            $variant = match ($grn->status) {
                'stored', 'accepted' => 'success',
                'qc_failed', 'rejected' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "grn-{$grn->id}",
                'type' => 'goods_receipt_note',
                'title' => "GRN #{$grn->grn_number}",
                'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Goods Receipt',
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.receiving.show', $grn),
                'icon' => 'archive-box',
            ];
        })->all();

        return [
            'key' => 'goods_receipts',
            'label' => 'Goods Receipts (GRN)',
            'icon' => 'archive-box',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.receiving.index'),
            'items' => $items,
        ];
    }

    /**
     * Search Inspection Acceptance Reports (IAR) by IAR Number or Invoice Number.
     */
    protected function searchInspectionReports(string $term, int $limit): array
    {
        $query = InspectionAcceptanceReport::query()
            ->where(function ($q) use ($term): void {
                $q->where('iar_number', 'like', "%{$term}%")
                    ->orWhere('invoice_number', 'like', "%{$term}%");
            });

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (InspectionAcceptanceReport $iar): array {
            $subtitleParts = [];
            if ($iar->invoice_number) {
                $subtitleParts[] = "Invoice: {$iar->invoice_number}";
            }
            if ($iar->delivery_status) {
                $subtitleParts[] = ucfirst($iar->delivery_status);
            }

            $statusText = ucfirst(str_replace('_', ' ', $iar->status ?? 'pending'));
            $variant = match ($iar->status) {
                'accepted', 'transmitted_to_coa' => 'success',
                'rejected' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "iar-{$iar->id}",
                'type' => 'inspection_acceptance_report',
                'title' => "IAR #{$iar->iar_number}",
                'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Inspection Report',
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.logistics.iar.show', $iar),
                'icon' => 'clipboard-document-list',
            ];
        })->all();

        return [
            'key' => 'inspection_reports',
            'label' => 'Inspection Reports (IAR)',
            'icon' => 'clipboard-document-list',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.logistics.iar.index'),
            'items' => $items,
        ];
    }

    /**
     * Search Stock Transfers by Transfer Number.
     */
    protected function searchStockTransfers(string $term, int $limit): array
    {
        $query = StockTransfer::query()
            ->with(['sourceLocation', 'destinationLocation'])
            ->where('transfer_number', 'like', "%{$term}%");

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (StockTransfer $transfer): array {
            $src = $transfer->sourceLocation?->name ?: 'Origin';
            $dst = $transfer->destinationLocation?->name ?: 'Destination';

            $statusText = ucfirst(str_replace('_', ' ', $transfer->status ?? 'pending'));
            $variant = match ($transfer->status) {
                'completed', 'received' => 'success',
                'cancelled' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "transfer-{$transfer->id}",
                'type' => 'stock_transfer',
                'title' => "Transfer #{$transfer->transfer_number}",
                'subtitle' => "{$src} → {$dst}",
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.transfers.show', $transfer),
                'icon' => 'arrows-right-left',
            ];
        })->all();

        return [
            'key' => 'stock_transfers',
            'label' => 'Stock Transfers',
            'icon' => 'arrows-right-left',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.transfers.index'),
            'items' => $items,
        ];
    }

    /**
     * Search Cycle Counts by Document Number.
     */
    protected function searchCycleCounts(string $term, int $limit): array
    {
        $query = CycleCountDoc::query()
            ->with('location')
            ->where('document_number', 'like', "%{$term}%");

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (CycleCountDoc $count): array {
            $locationName = $count->location?->name ?: 'All Locations';
            $statusText = ucfirst(str_replace('_', ' ', $count->status ?? 'draft'));
            $variant = match ($count->status) {
                'approved', 'reconciled' => 'success',
                'cancelled' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "cycle-count-{$count->id}",
                'type' => 'cycle_count',
                'title' => "Cycle Count #{$count->document_number}",
                'subtitle' => "Location: {$locationName}",
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.cycle-counts.show', $count),
                'icon' => 'table-cells',
            ];
        })->all();

        return [
            'key' => 'cycle_counts',
            'label' => 'Cycle Counts',
            'icon' => 'table-cells',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.cycle-counts.index'),
            'items' => $items,
        ];
    }

    /**
     * Search Warehouse Tasks by Task Number.
     */
    protected function searchWarehouseTasks(string $term, int $limit): array
    {
        $query = WarehouseTask::query()
            ->with('item')
            ->where('task_number', 'like', "%{$term}%");

        $total = (clone $query)->count();
        $records = $query->latest('id')->take($limit)->get();

        $items = $records->map(function (WarehouseTask $task): array {
            $taskType = $task->task_type?->label() ?? ucfirst($task->task_type?->value ?? 'Task');
            $subtitleParts = [];
            if ($task->item?->name) {
                $subtitleParts[] = $task->item->name;
            }
            $subtitleParts[] = "Type: {$taskType}";

            $statusText = ucfirst(str_replace('_', ' ', $task->status?->value ?? $task->status ?? 'pending'));
            $variant = match ($task->status?->value ?? $task->status) {
                'completed' => 'success',
                'cancelled' => 'danger',
                default => 'neutral',
            };

            return [
                'id' => "task-{$task->id}",
                'type' => 'warehouse_task',
                'title' => "Task #{$task->task_number}",
                'subtitle' => implode(' · ', $subtitleParts),
                'badge' => $statusText,
                'badge_variant' => $variant,
                'url' => route('inventory.warehouse-tasks.show', $task),
                'icon' => 'tag',
            ];
        })->all();

        return [
            'key' => 'warehouse_tasks',
            'label' => 'Warehouse Tasks',
            'icon' => 'tag',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.warehouse-tasks.index'),
            'items' => $items,
        ];
    }

    /**
     * Search Storage Locations by Code or Name.
     */
    protected function searchStorageLocations(string $term, int $limit): array
    {
        $query = StorageLocation::query()
            ->where(function ($q) use ($term): void {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });

        $total = (clone $query)->count();
        $records = $query->orderBy('code')->take($limit)->get();

        $items = $records->map(function (StorageLocation $loc): array {
            $subtitleParts = [];
            if ($loc->zone) {
                $subtitleParts[] = "Zone: {$loc->zone}";
            }
            if ($loc->type) {
                $subtitleParts[] = 'Type: '.ucfirst($loc->type);
            }

            return [
                'id' => "location-{$loc->id}",
                'type' => 'storage_location',
                'title' => "{$loc->code} - {$loc->name}",
                'subtitle' => ! empty($subtitleParts) ? implode(' · ', $subtitleParts) : 'Storage Location',
                'badge' => ucfirst($loc->status ?? 'active'),
                'badge_variant' => $loc->status === 'active' ? 'success' : 'neutral',
                'url' => route('inventory.storage-locations', ['search' => $loc->code]),
                'icon' => 'map-pin',
            ];
        })->all();

        return [
            'key' => 'storage_locations',
            'label' => 'Storage Locations',
            'icon' => 'map-pin',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('inventory.storage-locations', ['search' => $term]),
            'items' => $items,
        ];
    }

    /**
     * Search Users / Employees by Name, First Name, Surname, Employee ID, Email, or Department.
     */
    protected function searchUsers(string $term, int $limit): array
    {
        $isEmailQuery = str_contains($term, '@');

        $query = User::query()
            ->where(function ($q) use ($term, $isEmailQuery): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('surname', 'like', "%{$term}%")
                    ->orWhere('employee_id', 'like', "%{$term}%");

                if ($isEmailQuery) {
                    $q->orWhere('email', 'like', "%{$term}%");
                }
            });

        $total = (clone $query)->count();

        // If no user matched by name or ID, allow fallback to email for specific email/username searches
        if ($total === 0 && ! $isEmailQuery && strlen($term) >= 4 && ! str_contains($term, ' ')) {
            $query = User::query()->where('email', 'like', "%{$term}%");
            $total = (clone $query)->count();
        }

        $records = $query
            ->orderByRaw("CASE
                WHEN name LIKE ? THEN 1
                WHEN first_name LIKE ? THEN 2
                WHEN surname LIKE ? THEN 3
                WHEN employee_id LIKE ? THEN 4
                ELSE 5 END",
                ["{$term}%", "{$term}%", "{$term}%", "{$term}%"]
            )
            ->take($limit)
            ->get();

        $items = $records->map(function (User $userRecord): array {
            $subtitleParts = [];
            if ($userRecord->employee_id) {
                $subtitleParts[] = "ID: {$userRecord->employee_id}";
            }
            $subtitleParts[] = $userRecord->email;
            if ($userRecord->department) {
                $subtitleParts[] = $userRecord->department;
            }

            return [
                'id' => "user-{$userRecord->id}",
                'type' => 'user',
                'title' => $userRecord->name,
                'subtitle' => implode(' · ', $subtitleParts),
                'badge' => $userRecord->role?->label() ?? 'User',
                'badge_variant' => 'primary',
                'url' => route('admin.users.show', $userRecord),
                'icon' => 'user-circle',
            ];
        })->all();

        return [
            'key' => 'users',
            'label' => 'Users & Employees',
            'icon' => 'user-circle',
            'total' => $total,
            'has_more' => $total > $limit,
            'view_all_url' => route('admin.users.index', ['search' => $term]),
            'items' => $items,
        ];
    }
}
