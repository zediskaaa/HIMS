<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

set_exception_handler(static function (\Throwable $exception): void {
    echo json_encode(['error_class' => get_class($exception)], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
});

$name = config('database.default');
$connection = config('database.connections.'.$name);
$host = ! empty($connection['url'] ?? null)
    ? parse_url($connection['url'], PHP_URL_HOST)
    : ($connection['host'] ?? null);

if (($argv[1] ?? null) === 'tables') {
    echo json_encode(\Illuminate\Support\Facades\Schema::getTableListing(), JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if (($argv[1] ?? null) === 'counts') {
    $tables = [
        'suppliers', 'supplier_contacts', 'supplier_accreditations', 'supplier_contracts',
        'supplier_products', 'supplier_prices', 'inventory_items', 'item_categories',
        'item_batches', 'item_stock_levels', 'item_unit_conversions', 'storage_locations',
        'cost_centers', 'users', 'purchase_orders', 'po_line_items', 'purchase_requests',
        'pr_line_items', 'material_requisitions', 'material_requisition_lines',
        'stock_movements', 'goods_receipt_notes', 'notifications',
    ];
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = \Illuminate\Support\Facades\DB::table($table)->count();
    }
    echo json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if (($argv[1] ?? null) === 'columns') {
    $tables = ['suppliers', 'supplier_products', 'inventory_items', 'item_categories',
        'item_stock_levels', 'item_batches', 'storage_locations', 'cost_centers',
        'users', 'purchase_orders', 'po_line_items', 'material_requisitions',
        'material_requisition_lines', 'stock_movements'];
    $columns = [];
    foreach ($tables as $table) {
        $columns[$table] = \Illuminate\Support\Facades\Schema::getColumnListing($table);
    }
    echo json_encode($columns, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if (($argv[1] ?? null) === 'masters') {
    $missing = static function (string $table, string $column): int {
        return \Illuminate\Support\Facades\DB::table($table)
            ->where(static fn ($query) => $query->whereNull($column)->orWhere($column, ''))
            ->count();
    };
    $suppliers = \Illuminate\Support\Facades\DB::table('suppliers')
        ->select('id', 'name', 'status', 'accreditation_status', 'standard_lead_time_days')
        ->orderBy('id')->get();
    $items = \Illuminate\Support\Facades\DB::table('inventory_items')
        ->select('id', 'name', 'sku', 'category_id', 'supplier_id', 'default_location_id',
            'unit', 'quantity_on_hand', 'reserved_quantity', 'reorder_level', 'safety_stock', 'status')
        ->orderBy('id')->get();
    $costCenters = \Illuminate\Support\Facades\DB::table('cost_centers')
        ->select('id', 'name', 'code', 'department', 'is_active')->orderBy('id')->get();
    $departments = \Illuminate\Support\Facades\DB::table('users')
        ->select('department')->selectRaw('COUNT(*) AS user_count')
        ->groupBy('department')->orderBy('department')->get();
    $result = [
        'suppliers' => $suppliers,
        'supplier_missing' => [
            'name' => $missing('suppliers', 'name'),
            'contact_person' => $missing('suppliers', 'contact_person'),
            'email' => $missing('suppliers', 'email'),
            'phone' => $missing('suppliers', 'phone'),
            'address' => $missing('suppliers', 'address'),
            'billing_address' => $missing('suppliers', 'billing_address'),
            'delivery_address' => $missing('suppliers', 'delivery_address'),
            'payment_terms' => $missing('suppliers', 'payment_terms'),
        ],
        'item_categories' => \Illuminate\Support\Facades\DB::table('item_categories')
            ->select('id', 'name', 'code', 'is_active')->orderBy('id')->get(),
        'items' => $items,
        'item_missing' => [
            'name' => $missing('inventory_items', 'name'),
            'sku' => $missing('inventory_items', 'sku'),
            'unit' => $missing('inventory_items', 'unit'),
            'barcode_value' => $missing('inventory_items', 'barcode_value'),
            'gtin' => $missing('inventory_items', 'gtin'),
            'category_id' => \Illuminate\Support\Facades\DB::table('inventory_items')->whereNull('category_id')->count(),
            'supplier_id' => \Illuminate\Support\Facades\DB::table('inventory_items')->whereNull('supplier_id')->count(),
            'default_location_id' => \Illuminate\Support\Facades\DB::table('inventory_items')->whereNull('default_location_id')->count(),
        ],
        'cost_centers' => $costCenters,
        'user_departments' => $departments,
        'locations' => \Illuminate\Support\Facades\DB::table('storage_locations')
            ->select('id', 'name', 'code', 'type', 'parent_id', 'status', 'storage_classification', 'temperature_classification')
            ->orderBy('id')->get(),
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if (($argv[1] ?? null) === 'integrity') {
    $count = static fn (string $sql): int => (int) (\Illuminate\Support\Facades\DB::selectOne($sql)->total ?? 0);
    $rowIds = static fn (string $sql): array => array_map(
        static fn ($row) => (array) $row,
        \Illuminate\Support\Facades\DB::select($sql)
    );
    $result = [
        'orphan_item_categories' => $count('SELECT COUNT(*) total FROM inventory_items i LEFT JOIN item_categories c ON c.id = i.category_id WHERE i.category_id IS NOT NULL AND c.id IS NULL'),
        'orphan_item_suppliers' => $count('SELECT COUNT(*) total FROM inventory_items i LEFT JOIN suppliers s ON s.id = i.supplier_id WHERE i.supplier_id IS NOT NULL AND s.id IS NULL'),
        'orphan_item_default_locations' => $count('SELECT COUNT(*) total FROM inventory_items i LEFT JOIN storage_locations l ON l.id = i.default_location_id WHERE i.default_location_id IS NOT NULL AND l.id IS NULL'),
        'orphan_stock_items' => $count('SELECT COUNT(*) total FROM item_stock_levels sl LEFT JOIN inventory_items i ON i.id = sl.item_id WHERE i.id IS NULL'),
        'orphan_stock_locations' => $count('SELECT COUNT(*) total FROM item_stock_levels sl LEFT JOIN storage_locations l ON l.id = sl.storage_location_id WHERE l.id IS NULL'),
        'stock_batch_item_mismatches' => $count('SELECT COUNT(*) total FROM item_stock_levels sl JOIN item_batches b ON b.id = sl.item_batch_id WHERE sl.item_id <> b.item_id'),
        'negative_stock_balances' => $count('SELECT COUNT(*) total FROM item_stock_levels WHERE quantity < 0 OR reserved_quantity < 0 OR quarantined_quantity < 0 OR blocked_quantity < 0 OR in_transit_quantity < 0'),
        'stock_reserved_exceeds_quantity' => $count('SELECT COUNT(*) total FROM item_stock_levels WHERE reserved_quantity > quantity'),
        'stock_on_inactive_locations' => $count("SELECT COUNT(*) total FROM item_stock_levels sl JOIN storage_locations l ON l.id = sl.storage_location_id WHERE l.status <> 'active' AND sl.quantity > 0"),
        'stock_cache_mismatches' => $rowIds('SELECT i.id, i.sku, i.quantity_on_hand item_quantity, COALESCE(SUM(sl.quantity), 0) level_quantity, i.reserved_quantity item_reserved, COALESCE(SUM(sl.reserved_quantity), 0) level_reserved FROM inventory_items i LEFT JOIN item_stock_levels sl ON sl.item_id = i.id GROUP BY i.id, i.sku, i.quantity_on_hand, i.reserved_quantity HAVING item_quantity <> level_quantity OR item_reserved <> level_reserved ORDER BY i.id'),
        'duplicate_stock_keys' => $rowIds('SELECT item_id, storage_location_id, item_batch_id, COUNT(*) total FROM item_stock_levels GROUP BY item_id, storage_location_id, item_batch_id HAVING COUNT(*) > 1'),
        'orphan_supplier_products' => $count('SELECT COUNT(*) total FROM supplier_products sp LEFT JOIN suppliers s ON s.id = sp.supplier_id LEFT JOIN inventory_items i ON i.id = sp.item_id WHERE s.id IS NULL OR i.id IS NULL'),
        'primary_supplier_links_without_product' => $rowIds('SELECT i.id item_id, i.sku, i.supplier_id FROM inventory_items i LEFT JOIN supplier_products sp ON sp.item_id = i.id AND sp.supplier_id = i.supplier_id WHERE i.supplier_id IS NOT NULL AND sp.id IS NULL ORDER BY i.id'),
        'orphan_po_suppliers' => $count('SELECT COUNT(*) total FROM purchase_orders p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.supplier_id IS NOT NULL AND s.id IS NULL'),
        'orphan_po_line_items' => $count('SELECT COUNT(*) total FROM po_line_items l LEFT JOIN inventory_items i ON i.id = l.item_id WHERE i.id IS NULL'),
        'po_without_legacy_or_lines' => $rowIds('SELECT p.id, p.po_number FROM purchase_orders p LEFT JOIN po_line_items l ON l.purchase_order_id = p.id WHERE p.item_id IS NULL GROUP BY p.id, p.po_number HAVING COUNT(l.id) = 0'),
        'po_line_quantity_anomalies' => $rowIds('SELECT id, purchase_order_id, ordered_quantity, received_quantity, invoiced_quantity FROM po_line_items WHERE ordered_quantity <= 0 OR received_quantity < 0 OR invoiced_quantity < 0 OR received_quantity > ordered_quantity OR invoiced_quantity > ordered_quantity ORDER BY id'),
        'orphan_mr_cost_centers' => $count('SELECT COUNT(*) total FROM material_requisitions r LEFT JOIN cost_centers c ON c.id = r.cost_center_id WHERE r.cost_center_id IS NOT NULL AND c.id IS NULL'),
        'orphan_mr_items' => $count('SELECT COUNT(*) total FROM material_requisition_lines l LEFT JOIN inventory_items i ON i.id = l.item_id WHERE i.id IS NULL'),
        'mr_line_quantity_anomalies' => $rowIds('SELECT id, material_requisition_id, requested_quantity, reserved_quantity, issued_quantity FROM material_requisition_lines WHERE requested_quantity <= 0 OR reserved_quantity < 0 OR issued_quantity < 0 OR issued_quantity > requested_quantity ORDER BY id'),
        'orphan_movement_items' => $count('SELECT COUNT(*) total FROM stock_movements m LEFT JOIN inventory_items i ON i.id = m.item_id WHERE i.id IS NULL'),
        'orphan_movement_from_locations' => $count('SELECT COUNT(*) total FROM stock_movements m LEFT JOIN storage_locations l ON l.id = m.from_location_id WHERE m.from_location_id IS NOT NULL AND l.id IS NULL'),
        'orphan_movement_to_locations' => $count('SELECT COUNT(*) total FROM stock_movements m LEFT JOIN storage_locations l ON l.id = m.to_location_id WHERE m.to_location_id IS NOT NULL AND l.id IS NULL'),
        'duplicate_supplier_names' => $rowIds('SELECT LOWER(TRIM(name)) normalized_name, COUNT(*) total FROM suppliers GROUP BY LOWER(TRIM(name)) HAVING COUNT(*) > 1'),
        'duplicate_item_skus' => $rowIds('SELECT sku, COUNT(*) total FROM inventory_items GROUP BY sku HAVING COUNT(*) > 1'),
        'duplicate_location_codes' => $rowIds('SELECT code, COUNT(*) total FROM storage_locations GROUP BY code HAVING COUNT(*) > 1'),
        'duplicate_cost_center_codes' => $rowIds('SELECT code, COUNT(*) total FROM cost_centers GROUP BY code HAVING COUNT(*) > 1'),
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if (($argv[1] ?? null) === 'detail') {
    $rows = static fn (string $sql): array => array_map(
        static fn ($row) => (array) $row,
        \Illuminate\Support\Facades\DB::select($sql)
    );
    $result = [
        'stock_discrepancy_items' => $rows('SELECT i.id, i.sku, i.name, i.default_location_id, i.is_batch_tracked, i.is_expiry_tracked, i.quantity_on_hand, i.reserved_quantity, (SELECT COUNT(*) FROM stock_movements m WHERE m.item_id = i.id) movement_count, (SELECT COUNT(*) FROM item_batches b WHERE b.item_id = i.id) batch_count FROM inventory_items i WHERE i.id IN (1,2,30001,30003,30004,30012) ORDER BY i.id'),
        'discrepancy_movements' => $rows('SELECT item_id, movement_type, COUNT(*) movement_count, SUM(quantity) movement_quantity FROM stock_movements WHERE item_id IN (1,2,30001,30003,30004,30012) GROUP BY item_id, movement_type ORDER BY item_id, movement_type'),
        'reserved_balance_evidence' => $rows('SELECT i.id item_id, i.sku, i.quantity_on_hand, i.reserved_quantity, i.unit_cost, i.total_value, sl.storage_location_id, sl.item_batch_id, sl.quantity level_quantity, sl.reserved_quantity level_reserved FROM inventory_items i JOIN item_stock_levels sl ON sl.item_id = i.id WHERE i.id IN (1,2) ORDER BY i.id, sl.id'),
        'requisition_reservations' => $rows('SELECT l.item_id, r.status requisition_status, SUM(l.reserved_quantity) reserved_quantity FROM material_requisition_lines l JOIN material_requisitions r ON r.id = l.material_requisition_id WHERE l.item_id IN (1,2) GROUP BY l.item_id, r.status'),
        'discrepancy_receipts' => $rows('SELECT l.item_id, SUM(l.received_quantity) receipt_quantity, SUM(l.accepted_quantity) accepted_quantity, SUM(l.rejected_quantity) rejected_quantity FROM grn_line_items l WHERE l.item_id IN (1,2,30001,30003,30004,30012) GROUP BY l.item_id'),
        'po_three' => $rows('SELECT p.id, p.po_number, p.supplier_id, p.item_id legacy_item_id, p.quantity legacy_quantity, p.status, l.id line_id, l.item_id line_item_id, l.ordered_quantity, l.received_quantity, l.invoiced_quantity, l.line_status FROM purchase_orders p JOIN po_line_items l ON l.purchase_order_id = p.id WHERE p.id = 3'),
        'po_three_receipts' => $rows('SELECT g.id receipt_id, g.purchase_order_id, l.item_id, l.ordered_quantity, l.received_quantity, l.accepted_quantity, l.rejected_quantity FROM goods_receipt_notes g JOIN grn_line_items l ON l.goods_receipt_note_id = g.id WHERE g.purchase_order_id = 3'),
        'po_three_quality' => $rows('SELECT q.id, q.grn_line_item_id, q.item_id, q.inspection_status, q.accepted_quantity, q.rejected_quantity FROM quality_inspections q JOIN grn_line_items l ON l.id = q.grn_line_item_id JOIN goods_receipt_notes g ON g.id = l.goods_receipt_note_id WHERE g.purchase_order_id = 3'),
        'po_three_iar' => $rows('SELECT id, goods_receipt_note_id, inspection_status, delivery_status, status FROM inspection_acceptance_reports WHERE purchase_order_id = 3'),
        'po_three_shipments' => $rows('SELECT id, status FROM shipments WHERE purchase_order_id = 3'),
        'po_three_movement_summary' => $rows('SELECT movement_type, COUNT(*) total, SUM(quantity) quantity FROM stock_movements WHERE item_id = 30011 GROUP BY movement_type'),
        'po_three_procurement_audit' => $rows("SELECT entity_name, action_type, COUNT(*) total FROM procurement_audit_logs WHERE entity_id = 3 AND entity_name LIKE '%PurchaseOrder%' GROUP BY entity_name, action_type"),
        'po_three_revisions' => $rows('SELECT id, revision_number, status FROM po_revisions WHERE purchase_order_id = 3'),
        'supplier_link_evidence' => $rows('SELECT i.id item_id, i.sku, i.supplier_id, s.status supplier_status, s.accreditation_status, i.status item_status, (SELECT COUNT(*) FROM purchase_orders p JOIN po_line_items l ON l.purchase_order_id = p.id WHERE p.supplier_id = i.supplier_id AND l.item_id = i.id) po_line_count FROM inventory_items i JOIN suppliers s ON s.id = i.supplier_id WHERE i.supplier_id IS NOT NULL ORDER BY i.id'),
        'items_missing_classification' => $rows('SELECT id, sku, name, category_id, supplier_id, default_location_id, status, storage_classification, temperature_classification FROM inventory_items WHERE category_id IS NULL OR default_location_id IS NULL ORDER BY id'),
        'missing_default_location_stock' => $rows('SELECT sl.item_id, sl.storage_location_id, l.code location_code, l.status location_status, SUM(sl.quantity) quantity FROM item_stock_levels sl JOIN inventory_items i ON i.id = sl.item_id JOIN storage_locations l ON l.id = sl.storage_location_id WHERE i.default_location_id IS NULL GROUP BY sl.item_id, sl.storage_location_id, l.code, l.status ORDER BY sl.item_id, sl.storage_location_id'),
        'requisition_center_mapping' => $rows('SELECT r.id, r.requisition_number, r.department requisition_department, c.department center_department, c.code center_code, r.status, r.cost_center_id FROM material_requisitions r LEFT JOIN cost_centers c ON c.id = r.cost_center_id ORDER BY r.id'),
        'orphan_location_parents' => $rows('SELECT l.id, l.code, l.parent_id FROM storage_locations l LEFT JOIN storage_locations p ON p.id = l.parent_id WHERE l.parent_id IS NOT NULL AND p.id IS NULL'),
        'supplier_statuses' => $rows('SELECT status, accreditation_status, COUNT(*) total FROM suppliers GROUP BY status, accreditation_status ORDER BY status, accreditation_status'),
        'item_statuses' => $rows('SELECT status, COUNT(*) total FROM inventory_items GROUP BY status ORDER BY status'),
        'location_statuses' => $rows('SELECT status, COUNT(*) total FROM storage_locations GROUP BY status ORDER BY status'),
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

if (($argv[1] ?? null) === 'workflows') {
    $rows = static fn (string $sql): array => array_map(
        static fn ($row) => (array) $row,
        \Illuminate\Support\Facades\DB::select($sql)
    );
    $result = [
        'approved_supplier_evidence' => $rows("SELECT s.id, s.name, s.approved_by IS NOT NULL has_approver, s.last_reviewed_at IS NOT NULL has_review_time, (SELECT COUNT(*) FROM supplier_accreditations a WHERE a.supplier_id = s.id AND a.status = 'approved') approved_cycle_count, (SELECT COUNT(*) FROM supplier_documents d WHERE d.supplier_id = s.id AND d.verification_status = 'verified') verified_document_count FROM suppliers s WHERE s.accreditation_status = 'approved' ORDER BY s.id"),
        'budget_summary' => $rows('SELECT c.id cost_center_id, c.code, b.fiscal_year, b.allocated_budget, b.soft_encumbered, b.hard_encumbered, b.spent_amount FROM cost_centers c LEFT JOIN cost_center_budgets b ON b.cost_center_id = c.id ORDER BY c.id, b.fiscal_year'),
        'location_category_rules' => $rows('SELECT l.code location_code, c.code category_code FROM storage_location_category_rules r JOIN storage_locations l ON l.id = r.storage_location_id JOIN item_categories c ON c.id = r.item_category_id ORDER BY l.code, c.code'),
        'movement_anomalies' => $rows("SELECT movement_type, COUNT(*) total FROM stock_movements WHERE (quantity <= 0 AND movement_type <> 'adjustment') OR (movement_type IN ('stock_out','transfer','disposal','issuance','return_to_supplier','transfer_dispatch','quality_reject') AND from_location_id IS NULL) OR (movement_type IN ('stock_in','transfer','quality_release','transfer_receipt','department_return') AND to_location_id IS NULL) GROUP BY movement_type"),
        'movement_types' => $rows('SELECT movement_type, COUNT(*) total FROM stock_movements GROUP BY movement_type ORDER BY movement_type'),
        'archived_items_with_stock' => $rows("SELECT i.id, i.sku, i.quantity_on_hand, COALESCE(SUM(sl.quantity),0) level_quantity FROM inventory_items i LEFT JOIN item_stock_levels sl ON sl.item_id = i.id WHERE i.status = 'archived' GROUP BY i.id, i.sku, i.quantity_on_hand HAVING level_quantity > 0"),
        'expired_batches_with_stock' => $rows('SELECT b.id batch_id, i.sku, b.expiry_date, SUM(sl.quantity) quantity FROM item_batches b JOIN inventory_items i ON i.id = b.item_id JOIN item_stock_levels sl ON sl.item_batch_id = b.id WHERE b.expiry_date < CURRENT_DATE GROUP BY b.id, i.sku, b.expiry_date HAVING quantity > 0'),
        'inactive_default_locations' => $rows("SELECT i.id, i.sku, l.code FROM inventory_items i JOIN storage_locations l ON l.id = i.default_location_id WHERE l.status <> 'active'"),
        'purchase_order_statuses' => $rows('SELECT status, COUNT(*) total FROM purchase_orders GROUP BY status ORDER BY status'),
        'material_requisition_statuses' => $rows('SELECT status, COUNT(*) total FROM material_requisitions GROUP BY status ORDER BY status'),
        'cost_center_mismatch' => $rows('SELECT r.id, r.requisition_number, r.department requisition_department, c.department cost_center_department FROM material_requisitions r JOIN cost_centers c ON c.id = r.cost_center_id WHERE LOWER(TRIM(r.department)) <> LOWER(TRIM(c.department))'),
        'notification_recipient_types' => $rows('SELECT notifiable_type, COUNT(*) total FROM notifications GROUP BY notifiable_type'),
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

$connected = false;
$connectionError = null;
try {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    $connected = true;
} catch (\Throwable $exception) {
    $connectionError = get_class($exception);
}

echo json_encode([
    'environment' => app()->environment(),
    'driver' => $connection['driver'] ?? null,
    'url_configured' => ! empty($connection['url'] ?? null),
    'host_class' => $host === null ? 'none' : (in_array($host, ['127.0.0.1', 'localhost', '::1'], true) ? 'loopback' : 'non-loopback'),
    'sqlite_memory' => ($connection['database'] ?? null) === ':memory:',
    'connected' => $connected,
    'connection_error_class' => $connectionError,
], JSON_THROW_ON_ERROR).PHP_EOL;
