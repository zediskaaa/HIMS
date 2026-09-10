# HIMS Access and Audit Matrix

This document describes the authorization and audit contracts implemented by the application. The executable source of truth remains `App\Enums\Permission`, `App\Enums\UserRole`, controller middleware, and `App\Enums\AuditAction`.

## Role and navigation matrix

HIMS currently stores one role per user. Multiple-role aggregation is therefore not an implemented account capability. Inactive accounts receive no permissions.

| Role | Visible operational areas | Allowed actions (summary) | Administrative boundary |
|---|---|---|---|
| Super Administrator | All modules, including Audit Trail | Every defined permission | Dedicated Super Admin guard and panel |
| Administrator | Inventory, requisitions, procurement, reports, user management | Broad operational and user administration access, excluding explicitly separated audit and high-risk warehouse duties | Admin guard; cannot view Audit Trail |
| Inventory Manager | Inventory, Store Requisitions, Cycle Counts, warehouse, procurement, logistics, process reviews | Item/location/supplier/procurement management, requisition approval, stock/count/transfer operations, selected warehouse and review work | Staff guard; no user administration or Audit Trail |
| Warehouse Staff | Inventory, Store Requisitions, Cycle Counts, receiving, warehouse tasks, logistics | Receive, move, issue, count, transfer, execute tasks, print labels, and record custody | Staff guard; no master-data/procurement/user administration |
| Pharmacy Staff | Inventory, Store Requisitions, selected warehouse/logistics screens, reports | Dispense, create requisitions, inspect stock, and perform explicitly granted controlled-stock/logistics duties | Staff guard; no item/supplier/procurement administration |
| Auditor | Read-only inventory, reports, warehouse/logistics records, process reviews, Audit Trail | View and investigate organization-wide audit events | Staff guard; no mutations; assignable only by Super Administrator |
| Viewer | Read-only inventory, reports, warehouse/logistics records, process reviews | View only | Staff guard; no mutations and no organization-wide Audit Trail |

The project does not currently define separate Warehouse Manager, Receiving Officer, Quality Inspector, Pharmacist, Department Requester/Approver, or Procurement Staff/Approver account roles. Adding them requires an approved business authority and segregation-of-duties mapping; existing users must not be reassigned or granted access automatically.

## Permission-to-navigation and backend matrix

| Permission | Visible module/tab | Allowed page/action | Backend enforcement | Primary coverage |
|---|---|---|---|---|
| `view_inventory` | Items, stock, movements, locations, alerts | Read inventory records and summaries | Web and API controller `can:` middleware | `RoleBasedAccessTest`, `UiNavigationAuthorizationTest` |
| `manage_items` | Item management actions | Create/update inventory items | Web and API controller `can:` middleware | `RoleBasedAccessTest`, `InventoryItemApiTest` |
| `create_requisition`, `approve_requisition`, `issue_stock` | Store Requisitions and contextual actions | Create/cancel, approve/reject, issue/acknowledge as applicable | Web and API method middleware plus `IssuanceEngine` invariants | `MaterialRequisitionWorkflowTest`, `UiNavigationAuthorizationTest` |
| `perform_cycle_count`, `approve_adjustment` | Cycle Counts and contextual actions | View/schedule/count and approve/post variance | Web and API method middleware plus `CycleCountService` | `CycleCountWorkflowTest`, `UiNavigationAuthorizationTest` |
| `record_movements`, `transfer_stock`, `adjust_stock` | Movement, transfer, adjustment screens | Server-confirmed stock changes | Web and API method middleware plus inventory services | inventory workflow suites |
| `receive_purchase_order`, `inspect_stock` | Dock Receiving and QC | Receive, inspect, release/reject | Web and API method middleware plus receiving/QC services | enterprise inventory suites |
| Warehouse permissions | Warehouse Tasks and specialized warehouse screens | Plan, assign, execute, resolve, label, telemetry, controlled-stock operations | Granular web/API controller middleware | smart warehousing suites |
| Procurement permissions | Requisitions & POs, suppliers, sourcing actions | Request, source, evaluate, award, issue/approve PO | Granular web/API controller middleware and domain services | procurement and supplier suites |
| Logistics permissions | Documents & Logistics | View, upload, verify, inspect, accept, and custody actions | Granular controller method middleware | logistics suite |
| Process-review permissions | Process Reviews | View, create, approve, implement | Granular controller method middleware | process-review suite |
| `manage_users` | User Management and Access Control | Account administration and permission-matrix review | Admin controller middleware and `UserAccountService` | user/admin authentication suites |
| `view_audit_trail` | Audit Trail | Search, filter, paginate, and inspect immutable events | Audit controller `can:` middleware; Auditor and Super Administrator | `AuditTrailTest` |

Desktop and mobile navigation share `resources/views/layouts/partials/sidebar.blade.php`, so permission visibility and icon behavior have one rendering source. Navigation hiding is only an affordance; the corresponding server middleware remains authoritative.

## Audit-event coverage matrix

| Domain | Authoritative audit boundary | Representative events |
|---|---|---|
| Authentication and accounts | Laravel auth listeners, `LoginLockoutService`, `UserAccountService`, `UserObserver` | successful/failed login, logout, password change, lock/unlock, account create/update/deactivation/role change |
| Suppliers | `SupplierManagementService` and supplier evidence actions | create/update, submit, approve/reject, suspend/reactivate/inactivate, document/product/price/contract changes |
| Procurement | Procurement services and `ProcurementAuditService` adapter | purchase request, sourcing RFQ, quote/evaluation/award, PO issue/approval/amendment/receipt |
| Store Requisitions | `IssuanceEngine` transaction boundary | create, approve/reject/cancel, issue, handover acknowledgement |
| Inventory | Receiving, QC, transfer, cycle-count, and adjustment services | receipt, quarantine release/rejection, dispatch/receipt, count, variance/adjustment posting |
| Warehousing | Warehouse task, label, telemetry, narcotics, and consignment services | task lifecycle/exceptions, label print, monitored release, controlled-stock and consignment usage |
| Logistics | Document, shipment, inspection/acceptance, and custody services | document upload/verify/revise, shipment state, IAR lifecycle, custody transfer |
| Process review | `ProcessReviewService` transaction boundary | create/update/submit/approve/reject/implement |
| Automated work | The service/job that commits the business state | system-attributed replenishment and other implemented scheduled actions |

Every new audit row receives an immutable event UUID, actor and role snapshot, action, category, module, target/reference, outcome, source, correlation ID, safe before/after values, request context, an authoritative UTC timestamp, and the configured display timezone. Sensitive keys are removed centrally. Historical rows are not rewritten; their original `created_at` value remains available through the documented legacy timestamp fallback.

Audit records have no update or delete route, and `AuditLog` rejects model updates and deletes. The read surface is server-paginated and protected by `view_audit_trail`.
