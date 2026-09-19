<?php

namespace App\Services\Ai;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\ChainOfCustodyLog;
use App\Models\DemandPlan;
use App\Models\GoodsReceiptNote;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Centralized HIMS Capability Registry.
 *
 * Defines the complete capability architecture of the Hospital Inventory Management System.
 * Replaces narrow static keyword lists with a capability-driven domain model that governs:
 * 1. Scope detection (Is this query part of what HIMS does?)
 * 2. Entity attribution (Which models and resources are involved?)
 * 3. Operation detection (Search, view, check status, summarize, track, explain, compare)
 * 4. Authorization enforcement (Does the current actor hold permission?)
 * 5. Data retrieval routing (Which tools and services provide verified records?)
 */
class HimsCapabilityRegistry
{
    public const INVENTORY_ITEMS = 'inventory_items';
    public const INVENTORY_STOCK = 'inventory_stock';
    public const BATCHES_AND_EXPIRY = 'batches_and_expiry';
    public const STOCK_MOVEMENTS = 'stock_movements';
    public const STORAGE_LOCATIONS = 'storage_locations';
    public const SUPPLIERS = 'suppliers';
    public const PROCUREMENT = 'procurement';
    public const SHIPMENTS_DELIVERIES = 'shipments_deliveries';
    public const RECEIVING_INSPECTION = 'receiving_inspection';
    public const CHAIN_OF_CUSTODY = 'chain_of_custody';
    public const DEPARTMENTS = 'departments';
    public const USERS_AND_ACCOUNTS = 'users_and_accounts';
    public const ROLES_AND_PERMISSIONS = 'roles_and_permissions';
    public const REPORTS = 'reports';
    public const DASHBOARDS_AND_KPIS = 'dashboards_and_kpis';
    public const NOTIFICATIONS_AND_ALERTS = 'notifications_and_alerts';
    public const DEMAND_FORECASTING = 'demand_forecasting';
    public const AUDIT_TRAIL = 'audit_trail';
    public const SYSTEM_RECOVERY = 'system_recovery';
    public const DATA_IMPORT_EXPORT = 'data_import_export';
    public const SECURITY_AND_GOVERNANCE = 'security_and_governance';

    /**
     * Return all registered HIMS capabilities with metadata, permissions, and semantic cue patterns.
     *
     * @return array<string, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     category: string,
     *     entities: array<int, string>,
     *     operations: array<int, string>,
     *     required_permissions: array<int, Permission>,
     *     tool_method: ?string,
     *     cues: array<int, string>,
     *     patterns: array<int, string>,
     *     unauthorized_message: string
     * }>
     */
    public static function all(): array
    {
        return [
            self::INVENTORY_ITEMS => [
                'id' => self::INVENTORY_ITEMS,
                'name' => 'Inventory Item Catalog',
                'description' => 'Item catalog, SKU, codes, brand name, generic name, category, item status, packaging unit, and specifications.',
                'category' => 'inventory',
                'entities' => [InventoryItem::class, ItemCategory::class],
                'operations' => ['search', 'view', 'check_status', 'filter', 'explain'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'searchInventory',
                'cues' => [
                    'item', 'items', 'medicine', 'medicines', 'gamot', 'supply', 'supplies', 'sku',
                    'brand', 'generic', 'category', 'unit', 'description', 'catalog', 'product',
                    'products', 'consumable', 'consumables', 'equipment', 'ppe', 'gloves', 'mask',
                    'syringe', 'alcohol', 'gauze', 'cotton', 'needle', 'bandage', 'catheter', 'dextrose',
                ],
                'patterns' => [
                    '/\b(?:may|meron|mayroon)\b[^.]{0,35}\b(?:ba|tayo|natin|available|item|gamot)\b/iu',
                    '/\b(?:do we have|is there any|do we carry|search for|look up|check if we have)\b/iu',
                    '/\b(?:anong|ano ang|what is the)\s+(?:sku|category|brand|generic name|unit)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view inventory item records. Please request inventory viewing access from your system administrator.',
            ],

            self::INVENTORY_STOCK => [
                'id' => self::INVENTORY_STOCK,
                'name' => 'Stock Levels & Availability',
                'description' => 'Current stock on hand, available stock for dispensing, reserved quantities, low stock, out of stock, and reorder levels.',
                'category' => 'inventory',
                'entities' => [InventoryItem::class],
                'operations' => ['check_status', 'count', 'summarize', 'filter', 'recommend'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'searchInventory',
                'cues' => [
                    'stock', 'stocks', 'quantity', 'on hand', 'available', 'reserved', 'low stock',
                    'out of stock', 'depleted', 'zero stock', 'reorder level', 'minimum stock',
                    'safety stock', 'paubos', 'ubos', 'naubos', 'kulang', 'ilan', 'marami',
                ],
                'patterns' => [
                    '/\b(?:low\s+stock|out\s+of\s+stock|running\s+low|zero\s+stock|depleted)\b/iu',
                    '/\b(?:paubos|ubos|kulang|marami|ilan\s+pa|available\s+stock|physical\s+stock)\b/iu',
                    '/\b(?:how\s+many|how\s+much\s+stock|ilan\s+ang\s+stock|ilan\s+ang\s+tira)\b/iu',
                    '/\b(?:need(?:s)?\s+(?:reorder|replenish|restock)|kailangan(?:g)?\s+(?:orderin|i-restock))\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view stock level data.',
            ],

            self::BATCHES_AND_EXPIRY => [
                'id' => self::BATCHES_AND_EXPIRY,
                'name' => 'Batches, Lots & Expiration',
                'description' => 'Batch and lot tracking, expiry horizons, FEFO ordering, non-expiring goods, expired stock, and near-expiry warnings.',
                'category' => 'inventory',
                'entities' => [ItemBatch::class, InventoryItem::class],
                'operations' => ['check_status', 'filter', 'view', 'track', 'summarize'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getExpiringBatches',
                'cues' => [
                    'batch', 'batches', 'lot', 'lots', 'expiry', 'expire', 'expired', 'expiration',
                    'expiring', 'shelf life', 'fefo', 'walang expiry', 'no expiry', 'panis',
                ],
                'patterns' => [
                    '/\b(?:walang|no|without|hindi\s+nag-e-expire)\b[^.]{0,25}\b(?:expir(?:y|e|ed|ing)|expiration)\b/iu',
                    '/\b(?:expired\s+na|already\s+expired|past\s+expiry|expired\s+stock)\b/iu',
                    '/\b(?:nearing\s+expiry|expiring\s+soon|malapit\s+nang\s+mag-expire|mag-e-expire)\b/iu',
                    '/\b(?:batch\s+number|lot\s+number|fefo)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view batch and expiry records.',
            ],

            self::STOCK_MOVEMENTS => [
                'id' => self::STOCK_MOVEMENTS,
                'name' => 'Stock Movements & Adjustments',
                'description' => 'Historical stock in, stock out, transfers, adjustments, cycle counts, wastage, reasons, and actor attribution.',
                'category' => 'inventory',
                'entities' => [StockMovement::class],
                'operations' => ['track', 'view', 'summarize', 'filter'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getStockMovements',
                'cues' => [
                    'movement', 'movements', 'stock in', 'stock out', 'transfer', 'transfers',
                    'adjustment', 'adjustments', 'cycle count', 'issuance', 'dispensed', 'consumed',
                    'consumption', 'galaw', 'nabawas', 'nadagdag', 'inilipat',
                ],
                'patterns' => [
                    '/\b(?:stock\s+movement(?:s)?|recent\s+movement(?:s)?|stock\s+in|stock\s+out)\b/iu',
                    '/\b(?:bakit\s+nabawas|why\s+did\s+the\s+stock\s+decrease|kailan\s+na-receive)\b/iu',
                    '/\b(?:who\s+recorded|sino\s+ang\s+nag-record|sino\s+naglabas)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view stock movement history.',
            ],

            self::STORAGE_LOCATIONS => [
                'id' => self::STORAGE_LOCATIONS,
                'name' => 'Storage Locations & Warehousing',
                'description' => 'Hospital storerooms, central warehouse, aisles, shelves, bins, cabinets, cold chain facilities, and location stock balances.',
                'category' => 'warehousing',
                'entities' => [StorageLocation::class],
                'operations' => ['view', 'search', 'filter', 'check_status'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getStorageLocations',
                'cues' => [
                    'location', 'locations', 'storage', 'storeroom', 'warehouse', 'aisle', 'shelf',
                    'bin', 'cabinet', 'cold chain', 'freezer', 'refrigerator', 'nasaan', 'saan nakatago',
                ],
                'patterns' => [
                    '/\b(?:saan|nasaan|where)\b[^.]{0,30}\b(?:naka-store|nakatago|makikita|located|stored)\b/iu',
                    '/\b(?:storage\s+location(?:s)?|list\s+of\s+storerooms|warehouse\s+location(?:s)?)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view warehouse and storage location information.',
            ],

            self::SUPPLIERS => [
                'id' => self::SUPPLIERS,
                'name' => 'Suppliers & Vendors',
                'description' => 'Supplier profiles, vendor accreditation, contact personnel, supplied catalog items, standard lead times, and compliance.',
                'category' => 'procurement',
                'entities' => [Supplier::class],
                'operations' => ['search', 'view', 'check_status', 'compare'],
                'required_permissions' => [Permission::ViewSuppliers],
                'tool_method' => 'searchSuppliers',
                'cues' => [
                    'supplier', 'suppliers', 'vendor', 'vendors', 'distributor', 'distributors',
                    'lead time', 'contact person', 'sino supplier', 'kanino galing', 'accreditation',
                ],
                'patterns' => [
                    '/\b(?:sino|who)\b[^.]{0,30}\b(?:supplier|supplies|vendor|provides)\b/iu',
                    '/\b(?:supplier\s+details|supplier\s+contact|lead\s+time|vendor\s+info)\b/iu',
                    '/\b(?:kanino\s+binibili|kanino\s+nanggaling|supplier\s+nito)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view supplier and vendor profiles. This action requires supplier viewing privileges.',
            ],

            self::PROCUREMENT => [
                'id' => self::PROCUREMENT,
                'name' => 'Procurement & Purchase Orders',
                'description' => 'Purchase requests, purchase orders (PO), revisions, RFQs, vendor quotations, approval chains, and procurement statuses.',
                'category' => 'procurement',
                'entities' => [PurchaseOrder::class, MaterialRequisition::class],
                'operations' => ['check_status', 'view', 'track', 'summarize'],
                'required_permissions' => [Permission::ViewProcurement],
                'tool_method' => 'getProcurementRecords',
                'cues' => [
                    'procurement', 'purchase order', 'purchase orders', 'po', 'purchase request',
                    'requisition', 'rfq', 'order', 'orders', 'approval', 'approved', 'pending po',
                    'status ng po', 'na-order na', 'bidding', 'quote',
                ],
                'patterns' => [
                    '/\b(?:purchase\s+order(?:s)?|pending\s+po|procurement\s+status|po\s+status)\b/iu',
                    '/\b(?:may\s+po\s+na|na-order\s+na\s+ba|status\s+ng\s+order|pending\s+procurement)\b/iu',
                    '/\b(?:approval\s+chain|approved\s+po|rejected\s+po)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view procurement and purchase order records.',
            ],

            self::SHIPMENTS_DELIVERIES => [
                'id' => self::SHIPMENTS_DELIVERIES,
                'name' => 'Shipments & Inbound Deliveries',
                'description' => 'Expected deliveries, shipment tracking, carriers, estimated arrival dates, overdue deliveries, and transit status.',
                'category' => 'logistics',
                'entities' => [Shipment::class],
                'operations' => ['track', 'check_status', 'filter', 'view'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getShipmentsAndDeliveries',
                'cues' => [
                    'shipment', 'shipments', 'delivery', 'deliveries', 'delayed', 'overdue', 'carrier',
                    'tracking', 'expected delivery', 'kailan darating', 'parating', 'padala',
                ],
                'patterns' => [
                    '/\b(?:delayed\s+deliver(?:y|ies)|overdue\s+shipment(?:s)?|pending\s+delivery)\b/iu',
                    '/\b(?:kailan\s+darating|darating\s+ba|status\s+ng\s+delivery|shipment\s+tracking)\b/iu',
                    '/\b(?:expected\s+arrival|delivery\s+date|incoming\s+shipment(?:s)?)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view shipment and delivery logistics.',
            ],

            self::RECEIVING_INSPECTION => [
                'id' => self::RECEIVING_INSPECTION,
                'name' => 'Receiving & Technical Inspection',
                'description' => 'Goods Receipt Notes (GRN), Inspection & Acceptance Reports (IAR), inspection findings, delivery discrepancies, and COA transmittal.',
                'category' => 'logistics',
                'entities' => [GoodsReceiptNote::class, InspectionAcceptanceReport::class],
                'operations' => ['view', 'check_status', 'track', 'summarize'],
                'required_permissions' => [Permission::ViewLogisticsRecords],
                'tool_method' => 'getReceivingRecords',
                'cues' => [
                    'receiving', 'grn', 'goods receipt', 'inspection', 'iar', 'acceptance',
                    'discrepancy', 'discrepancies', 'short delivery', 'damaged delivery', 'findings',
                ],
                'patterns' => [
                    '/\b(?:goods\s+receipt|grn|inspection\s+and\s+acceptance|iar)\b/iu',
                    '/\b(?:inspection\s+status|inspection\s+findings|short\s+delivery|damaged\s+goods)\b/iu',
                    '/\b(?:natanggap\s+na\s+ba|receiving\s+record(?:s)?)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view Goods Receipt Notes (GRN) or Inspection Acceptance Reports (IAR).',
            ],

            self::CHAIN_OF_CUSTODY => [
                'id' => self::CHAIN_OF_CUSTODY,
                'name' => 'Chain of Custody & Narcotics Vault',
                'description' => 'Immutable custody transfer logs, releasing and receiving signatories, dangerous drugs register, and high-value transport logs.',
                'category' => 'logistics',
                'entities' => [ChainOfCustodyLog::class],
                'operations' => ['track', 'view', 'check_status'],
                'required_permissions' => [Permission::ViewLogisticsRecords],
                'tool_method' => 'getChainOfCustodyRecords',
                'cues' => [
                    'custody', 'chain of custody', 'custody log', 'transfer of custody', 'signatory',
                    'narcotics', 'pdea', 'dangerous drugs', 'vault', 'regulated',
                ],
                'patterns' => [
                    '/\b(?:chain\s+of\s+custody|custody\s+log(?:s)?|custody\s+transfer)\b/iu',
                    '/\b(?:pdea|dangerous\s+drugs|narcotics\s+vault|regulated\s+drugs)\b/iu',
                    '/\b(?:who\s+received\s+custody|kanino\s+inilipat\s+ang\s+custody)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to access Chain of Custody logs or regulated dangerous drug registers.',
            ],

            self::DEPARTMENTS => [
                'id' => self::DEPARTMENTS,
                'name' => 'Departments & Material Requisitions',
                'description' => 'Department supply requests, requisitions (RIS), ward allocations, cost centers, and departmental consumption.',
                'category' => 'inventory',
                'entities' => [MaterialRequisition::class],
                'operations' => ['view', 'check_status', 'filter', 'summarize'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getDepartmentRequisitions',
                'cues' => [
                    'department', 'departments', 'requisition', 'requisitions', 'ris', 'ward',
                    'clinic', 'emergency room', 'pharmacy', 'laboratory', 'surgery', 'icu', 'request',
                ],
                'patterns' => [
                    '/\b(?:department\s+requisition(?:s)?|pending\s+requisition(?:s)?|supply\s+request)\b/iu',
                    '/\b(?:may\s+pending\s+request|sino\s+nag-request|department\s+request)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view departmental material requisitions.',
            ],

            self::USERS_AND_ACCOUNTS => [
                'id' => self::USERS_AND_ACCOUNTS,
                'name' => 'User Accounts & Directory',
                'description' => 'Authorized staff directory, user roles, department assignments, and account active/locked status (strictly redacting credentials).',
                'category' => 'administration',
                'entities' => [User::class],
                'operations' => ['view', 'search', 'check_status'],
                'required_permissions' => [Permission::ManageUsers],
                'tool_method' => 'getUserManagementInfo',
                'cues' => [
                    'user', 'users', 'staff', 'employee', 'employees', 'account', 'accounts',
                    'role', 'admin', 'inventory manager', 'pharmacist', 'lockout', 'locked out',
                ],
                'patterns' => [
                    '/\b(?:user\s+account(?:s)?|staff\s+member|user\s+list|account\s+status)\b/iu',
                    '/\b(?:sino\s+ang\s+admin|anong\s+department\s+ni|is\s+the\s+account\s+locked)\b/iu',
                    '/\b(?:who\s+is\s+the\s+admin|user\s+role(?:s)?)\b/iu',
                ],
                'unauthorized_message' => 'You do not have administrative permission to view user account records.',
            ],

            self::ROLES_AND_PERMISSIONS => [
                'id' => self::ROLES_AND_PERMISSIONS,
                'name' => 'Roles & Security Permissions',
                'description' => 'System permission matrix, role boundaries (Super Admin, Admin, Inventory Manager, Staff, Viewer), and access rights.',
                'category' => 'administration',
                'entities' => [User::class],
                'operations' => ['explain', 'view', 'check_status'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => null,
                'cues' => [
                    'permission', 'permissions', 'role', 'roles', 'access', 'rights', 'privilege',
                    'privileges', 'karapatan', 'kakayahan', 'ano ang pwede',
                ],
                'patterns' => [
                    '/\b(?:permission\s+matrix|role\s+permissions|what\s+can\s+a\s+role\s+do)\b/iu',
                    '/\b(?:anong\s+access|anong\s+permission|who\s+can\s+approve)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view the system permission matrix.',
            ],

            self::REPORTS => [
                'id' => self::REPORTS,
                'name' => 'Reports & Analytics',
                'description' => 'Standard and executive reports: inventory valuation, stock movement ledger, expiry reports, procurement summaries, shrinkage logs.',
                'category' => 'analytics',
                'entities' => [InventoryItem::class, StockMovement::class],
                'operations' => ['summarize', 'view', 'generate', 'explain'],
                'required_permissions' => [Permission::ViewReports],
                'tool_method' => 'getReportsCatalog',
                'cues' => [
                    'report', 'reports', 'inventory report', 'valuation report', 'movement report',
                    'expiry report', 'procurement report', 'generate report', 'ulat',
                ],
                'patterns' => [
                    '/\b(?:show\s+me\s+the\s+report|generate\s+report|inventory\s+report)\b/iu',
                    '/\b(?:magkano\s+inventory|total\s+value\s+of\s+inventory|inventory\s+valuation)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to generate or view HIMS operational reports.',
            ],

            self::DASHBOARDS_AND_KPIS => [
                'id' => self::DASHBOARDS_AND_KPIS,
                'name' => 'Dashboard & Executive KPIs',
                'description' => 'High-level operational overview: total catalog items, total on-hand stock, active low-stock alerts, pending purchase orders, and valuation.',
                'category' => 'analytics',
                'entities' => [InventoryItem::class],
                'operations' => ['summarize', 'view', 'check_status'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getDailySummary',
                'cues' => [
                    'dashboard', 'kpi', 'summary', 'overview', 'daily summary', 'status today',
                    'overall status', 'buod', 'kamusta inventory', 'kumusta inventory',
                ],
                'patterns' => [
                    '/\b(?:daily\s+summary|inventory\s+summary|stock\s+overview|dashboard\s+summary)\b/iu',
                    '/\b(?:kamusta\s+ang\s+inventory|kumusta\s+ang\s+inventory|overall\s+status)\b/iu',
                    '/\b(?:how\s+many\s+items\s+in\s+storage|total\s+stock\s+count)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view inventory dashboard metrics.',
            ],

            self::NOTIFICATIONS_AND_ALERTS => [
                'id' => self::NOTIFICATIONS_AND_ALERTS,
                'name' => 'Stock Alerts & Notifications',
                'description' => 'Critical stock alerts, low-stock warnings, expiry notifications, delayed shipment notices, and system alerts.',
                'category' => 'inventory',
                'entities' => [StockAlert::class],
                'operations' => ['view', 'check_status', 'filter'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getStockAlerts',
                'cues' => [
                    'alert', 'alerts', 'notification', 'notifications', 'warning', 'warnings',
                    'critical', 'babala', 'notipikasyon', 'dapat bantayan',
                ],
                'patterns' => [
                    '/\b(?:stock\s+alert(?:s)?|active\s+alert(?:s)?|critical\s+alert(?:s)?)\b/iu',
                    '/\b(?:may\s+bagong\s+notification|ano\s+yung\s+alerts|dapat\s+bantayan)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view stock alerts and notifications.',
            ],

            self::DEMAND_FORECASTING => [
                'id' => self::DEMAND_FORECASTING,
                'name' => 'Demand Forecasting & Planning',
                'description' => 'AI and statistical 30/60/90-day demand predictions, consumption trends, safety stock recommendations, and replenishment lead times.',
                'category' => 'analytics',
                'entities' => [DemandPlan::class, InventoryItem::class],
                'operations' => ['explain', 'view', 'check_status', 'recommend'],
                'required_permissions' => [Permission::ViewInventory],
                'tool_method' => 'getDemandForecast',
                'cues' => [
                    'forecast', 'forecasting', 'predicted demand', 'demand forecast', 'projected demand',
                    'future demand', 'hula', 'projection', 'trend', 'trends',
                ],
                'patterns' => [
                    '/\b(?:demand\s+forecast|predicted\s+demand|forecast\s+next\s+month)\b/iu',
                    '/\b(?:explain\s+(?:the\s+)?demand\s+forecast|consumption\s+trend)\b/iu',
                    '/\b(?:alin\s+ang\s+mataas\s+ang\s+demand|projected\s+consumption)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view demand forecast analytics.',
            ],

            self::AUDIT_TRAIL => [
                'id' => self::AUDIT_TRAIL,
                'name' => 'System Audit Trail',
                'description' => 'Immutable audit logging: user actions, stock adjustments, role changes, authentication events, timestamps, and IP attribution.',
                'category' => 'governance',
                'entities' => [AuditLog::class],
                'operations' => ['view', 'track', 'search'],
                'required_permissions' => [Permission::ViewAuditTrail],
                'tool_method' => 'getRecentAuditActivity',
                'cues' => [
                    'audit', 'audit trail', 'audit log', 'audit logs', 'history', 'who changed',
                    'who deleted', 'activity log', 'sinong nagbago', 'sino nag-issue',
                ],
                'patterns' => [
                    '/\b(?:audit\s+trail|audit\s+log(?:s)?|recent\s+audit\s+activity)\b/iu',
                    '/\b(?:who\s+changed\s+this|who\s+issued|sinong\s+nagbago|audit\s+record(?:s)?)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to access the HIMS Audit Trail. Audit logs require administrative privileges.',
            ],

            self::SYSTEM_RECOVERY => [
                'id' => self::SYSTEM_RECOVERY,
                'name' => 'System Recovery & Health',
                'description' => 'Self-healing recovery monitoring, failed operations, dead-letter jobs, retry status, error summaries, and operational recovery actions.',
                'category' => 'governance',
                'entities' => [SystemRecoveryRecord::class],
                'operations' => ['view', 'check_status', 'explain'],
                'required_permissions' => [Permission::ManageSystemRecovery],
                'tool_method' => 'getSystemRecoveryStatus',
                'cues' => [
                    'recovery', 'system recovery', 'failed operation', 'failed operations', 'system error',
                    'retry', 'dead letter', 'sira', 'aberya', 'recovery issues',
                ],
                'patterns' => [
                    '/\b(?:system\s+recovery|failed\s+operation(?:s)?|open\s+issues|recovery\s+status)\b/iu',
                    '/\b(?:may\s+failed\s+operations|system\s+error(?:s)?|retry\s+status)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to view HIMS System Recovery records. This requires system recovery administration privileges.',
            ],

            self::DATA_IMPORT_EXPORT => [
                'id' => self::DATA_IMPORT_EXPORT,
                'name' => 'Data Import & Export',
                'description' => 'Bulk CSV/Excel catalog and stock import, template downloads, preview parsing, data validation, and import error logs.',
                'category' => 'administration',
                'entities' => [InventoryItem::class],
                'operations' => ['check_status', 'explain', 'view'],
                'required_permissions' => [Permission::ManageItems],
                'tool_method' => null,
                'cues' => [
                    'import', 'export', 'csv', 'excel', 'template', 'upload data', 'bulk import',
                    'import error', 'validation error',
                ],
                'patterns' => [
                    '/\b(?:data\s+import|import\s+items|download\s+template|csv\s+import)\b/iu',
                    '/\b(?:validation\s+error(?:s)?|failed\s+import|how\s+to\s+import)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to manage data imports or item catalog modifications.',
            ],

            self::SECURITY_AND_GOVERNANCE => [
                'id' => self::SECURITY_AND_GOVERNANCE,
                'name' => 'Security & Privacy Governance',
                'description' => 'Multi-factor authentication (MFA/TOTP) status, session timeout settings, privacy compliance (DPA/GDPR requests), and data retention.',
                'category' => 'security',
                'entities' => [User::class],
                'operations' => ['check_status', 'explain'],
                'required_permissions' => [Permission::ManagePrivacyCompliance],
                'tool_method' => null,
                'cues' => [
                    'security', 'mfa', 'totp', 'authenticator', 'privacy', 'session timeout',
                    'data retention', 'retention sweep', 'incident', 'privacy request',
                ],
                'patterns' => [
                    '/\b(?:mfa\s+status|two-factor|session\s+timeout|privacy\s+governance)\b/iu',
                    '/\b(?:data\s+retention|privacy\s+request|security\s+incident)\b/iu',
                ],
                'unauthorized_message' => 'You do not have permission to access security governance and privacy management records.',
            ],
        ];
    }

    /**
     * Find a registered capability by identifier.
     */
    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /**
     * Find the best-matching HIMS capability for a given natural language query.
     */
    public static function findMatchingCapability(string $message): ?array
    {
        $normalized = Str::lower(trim($message));

        foreach (self::all() as $cap) {
            foreach ($cap['patterns'] as $pattern) {
                if (preg_match($pattern, $normalized) === 1) {
                    return $cap;
                }
            }

            foreach ($cap['cues'] as $cue) {
                if (preg_match('/\b' . preg_quote($cue, '/') . '\b/i', $normalized) === 1) {
                    return $cap;
                }
            }
        }

        return null;
    }

    /**
     * Determine whether an inquiry relates to HIMS capabilities, entities, or stored data.
     */
    public static function isHimsScope(string $message, ?string $candidateItem = null): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        // 1. If an item or product candidate was extracted (e.g. "May Zonrox ba?", "May N95?")
        // it is unconditionally in scope as an inventory item inquiry.
        if ($candidateItem !== null && trim($candidateItem) !== '') {
            return true;
        }

        // 2. Check if query matches any registered capability pattern or cue
        if (self::findMatchingCapability($trimmed) !== null) {
            return true;
        }

        // 3. Check for general inventory inquiry syntactic frames in English or Tagalog
        $normalized = Str::lower($trimmed);
        $inquiryFrames = [
            '/\b(?:may|meron|mayroon)\b[^.]{0,35}\b(?:ba|tayo|natin|available|stock|pa)\b/iu',
            '/\b(?:do we have|is there any|do we carry|check if we have|search for)\b/iu',
            '/\b(?:sino|who)\b[^.]{0,25}\b(?:supplier|supplies|vendor|admin)\b/iu',
            '/\b(?:nasaan|saan)\b[^.]{0,25}\b(?:naka-store|nakatago|stock|location|makikita)\b/iu',
            '/\b(?:kailan|when)\b[^.]{0,25}\b(?:delivery|darating|arrival|shipment)\b/iu',
            '/\b(?:magkano|how much|total value)\b/iu',
            '/\b(?:show me|list|view|check|display|explain)\b[^.]{0,30}\b(?:stock|item|supplier|po|order|delivery|report|movement)\b/iu',
        ];

        foreach ($inquiryFrames as $frame) {
            if (preg_match($frame, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify if the actor holds the required permission to access a capability.
     */
    public static function isAuthorized(string $capabilityId, User $actor): bool
    {
        $cap = self::get($capabilityId);
        if ($cap === null) {
            return false;
        }

        if (empty($cap['required_permissions'])) {
            return true;
        }

        foreach ($cap['required_permissions'] as $perm) {
            if ($actor->can($perm->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a clear, polite explanation when an actor requests an unauthorized HIMS capability.
     */
    public static function getUnauthorizedMessage(string $capabilityId, ?User $actor = null): string
    {
        $cap = self::get($capabilityId);
        if ($cap !== null && ! empty($cap['unauthorized_message'])) {
            return $cap['unauthorized_message'];
        }

        return 'You do not have authorization to view this section of HIMS. Please contact your system administrator if you require access.';
    }

    /**
     * Describe the capabilities available to the current actor based on their active permissions.
     *
     * @return array<int, array{id: string, name: string, description: string}>
     */
    public static function describeAuthorizedCapabilities(User $actor): array
    {
        $authorized = [];

        foreach (self::all() as $cap) {
            if (self::isAuthorized($cap['id'], $actor)) {
                $authorized[] = [
                    'id' => $cap['id'],
                    'name' => $cap['name'],
                    'description' => $cap['description'],
                ];
            }
        }

        return $authorized;
    }
}
