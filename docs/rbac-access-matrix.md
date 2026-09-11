# HIMS Role-Based Access Control Matrix

Last audited: 2026-09-11

## Basis and enforcement

This matrix is based on the supplied RBAC brief, the responsibilities encoded in `UserRole`, the granular `Permission` abilities, and the actual web/API workflows. No separate business-responsibility reference artifact was supplied with the brief.

Access is enforced by Laravel Gates and controller middleware. Blade checks mirror those permissions so prohibited controls are not rendered. A hidden control is not treated as an authorization boundary. Inventory movements and audit records are append-only; their update/delete routes are intentionally absent.

Legend: **Yes** = allowed; **No** = forbidden; **Conditional** = allowed only for the stated workflow/status and, where applicable, a different maker/checker actor; **Summary** = aggregate or non-sensitive fields only. “Export” includes an available download/print action; the application currently has no generic bulk export for most pages.

## Supplier / Vendor Management

| Role | Tab / page | View | Create | Edit | Delete | Approve | Process | Export | Sensitive data |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Pharmacy Staff | Supplier directory/profile | No | No | No | No | No | No | No | No |
| Warehouse Staff | Supplier directory/profile | No | No | No | No | No | No | No | No |
| Inventory Manager | Directory; overview; contacts; compliance; products/pricing; contracts; performance; history | Yes | Yes | Yes | No permanent delete | No supplier accreditation decision | Submit/verify compliance; suspend controls unavailable | Document download | Contacts, TIN, addresses, evidence, pricing, contracts, notes |
| Administrator | Same detailed supplier tabs | Yes | No | No | No | Yes, independent accreditation/status decision | Compliance review/verification | Document download | Same detailed fields |
| Super Administrator | All supplier tabs/actions | Yes | Yes | Yes | No permanent delete | Yes, subject to maker/checker rules | Yes | Document download | All supplier fields |
| Auditor | All evidence/history tabs | Yes | No | No | No | No | No verification or status change | Document download | Audit-required supplier evidence and commercial history |
| Viewer | Directory; overview; performance summary | Summary | No | No | No | No | No | No compliance-file download | Legal/trade name, qualification status, lead time, regulated-product flag, aggregate performance only |

## Procurement and Sourcing

| Role | Tab / page | View | Create | Edit | Delete | Approve | Process | Export | Sensitive data |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Pharmacy Staff | Requisitions; PO fulfillment registry | Yes | Requisitions only | Own draft/request lifecycle only | No | No | Submit/cancel/acknowledge own requisition | No bulk export | No budgets, commercial terms, bid evaluations, or internal notes |
| Warehouse Staff | PO fulfillment registry | Yes | No | No | No | No | Receive authorized deliveries | No bulk export | No unit costs, totals, commercial terms, or evaluations |
| Inventory Manager | S2P; RFQ; bid matrix; PO; requisitions | Yes | Requisition, RFQ, quotation, award, PO | Managed sourcing/procurement records | No permanent delete | Requisition/PO where a different actor is required | Evaluate, award, issue, receive | Print/read reports where available | Budgets, prices, quotations, totals, evaluations, internal notes |
| Administrator | S2P/RFQ/evaluation evidence; PO; DOA policy | Yes | No operational document | Procurement policy/configuration only | No | Requisitions and delegated governance steps | Policy management; no sourcing, award, PO issue, or receiving | Read/print where available | Financial and evaluation evidence |
| Super Administrator | All procurement pages/actions | Yes | Yes | Yes | No permanent delete | Yes, subject to transaction maker/checker rules | Yes | Read/print where available | All procurement fields |
| Auditor | S2P; RFQ; evaluation; PO; procurement audit trail | Yes | No | No | No | No | No | Read/print evidence | Financials, quotations, evaluation scores, notes, and audit deltas for audit purposes |
| Viewer | PO status/fulfillment summary | Summary | No | No | No | No | No | No bulk export | No unit costs, totals, commercial terms, budgets, quotations, evaluations, or internal notes |

## Inventory Management

| Role | Tab / page | View | Create | Edit | Delete | Approve | Process | Export | Sensitive data |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Pharmacy Staff | Items/stock; movements history; reports; requisitions; transfers; QC | Yes | Requisition, issuance, transfer | No master-data edit | No | No | Issue/dispense, transfer, technical/QC inspection where assigned | Printable reports | Operational quantities and batch data needed for dispensing; no adjustment/audit administration |
| Warehouse Staff | Items/stock; movement history; receiving; QC; cycle counts; transfers; reports | Yes | Receipts, movements, counts, transfers | No item master or balance correction | No | No adjustment approval | Receive, move, inspect, count, transfer, issue | Labels/reports where granted | Physical stock, batches, locations, receiving evidence; commercial finance hidden |
| Inventory Manager | All inventory tabs | Yes | Item, movement, adjustment, count, transfer, receipt | Item/location/operational records | No historical movement delete | Adjustments/count variances with maker/checker restriction | Full storeroom processing | Reports and labels | All operational inventory data |
| Administrator | Items/stock/reports/forecast; adjustments; locations | Yes | No physical transaction | Location/topology configuration | No | Adjustment/requisition governance | Forecast generation/configuration; no issue/receive/transfer/count | Reports and labels | Oversight data; no reserved audit trail unless separately granted |
| Super Administrator | All inventory tabs/actions | Yes | Yes | Yes | No historical movement delete | Yes, subject to maker/checker rules | Yes | Reports and labels | All inventory data |
| Auditor | Items/stock; movement history; reports; warehouse task history | Yes | No | No | No | No | No | Printable reports | Read-only operational and historical evidence; no mutation controls |
| Viewer | Items/stock; movement history; aggregate reports | Summary/read | No | No | No | No | No | Printable non-sensitive reports | No adjustments, warehouse tasks, internal notes, or administrative audit data |

## Smart Warehousing

| Role | Tab / page | View | Create | Edit | Delete | Approve | Process | Export | Sensitive data |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Pharmacy Staff | Warehousing dashboard/tasks/topology | No | No | No | No | No | No | No | No |
| Warehouse Staff | Dashboard; task registry/detail; scan station; cycle counts; locations | Yes | No manual task planning | No topology | No | No | Start/scan/complete assigned work; receive/put-away/pick/dispatch; count | Task/location labels | Task scans, locations, batches, exceptions needed for work |
| Inventory Manager | All warehousing tabs | Yes | Tasks and locations | Assign/cancel tasks; topology; exception/telemetry controls | No historical delete | Count/adjustment variances | Full task execution and exception resolution | Labels | All warehouse operations |
| Administrator | Dashboard; warehouse tasks; topology/locations | Yes | Locations/topology | Locations/topology | No | No operational approval | Configuration and label generation only | Labels | Configuration and task oversight; no physical execution |
| Super Administrator | All warehousing tabs/actions | Yes | Yes | Yes | No historical delete | Yes where applicable | Yes | Labels | All warehouse data |
| Auditor | Dashboard; tasks; scans; locations/history | Yes | No | No | No | No | No | No operational label generation | Read-only task, scan, exception, and topology evidence |
| Viewer | Warehousing pages | No | No | No | No | No | No | No | No |

## Document Tracking and Logistics

| Role | Tab / page | View | Create | Edit | Delete | Approve | Process | Export | Sensitive data |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Pharmacy Staff | Dashboard; documents; shipments; IAR; custody ledger | Yes | No upload/shipment | No | No | No custodial acceptance | Verify documents; perform assigned technical inspection | Evidence download | Detailed operational evidence needed for inspection |
| Warehouse Staff | Dashboard; documents; shipments; IAR; custody ledger | Yes | Upload document; register shipment/IAR | Supersede document; dock arrival | No | No verification/acceptance | Inbound and custody operations | Evidence download | Detailed logistics and custody evidence |
| Inventory Manager | All logistics tabs | Yes | Documents, shipments, IAR | Supersede/dock records | No | Custodial acceptance | Verify documents; register/accept/transmit; no technical inspection | Evidence download | All logistics evidence |
| Administrator | Dashboard and detailed evidence | Yes | No | No | No | No | No | Evidence download | Read-only detailed logistics evidence |
| Super Administrator | All logistics tabs/actions | Yes | Yes | Yes/version | No destructive delete | Yes | Yes | Evidence download | All logistics evidence |
| Auditor | Dashboard; documents; shipments; IAR; custody ledger | Yes | No | No | No | No | No verification, registration, upload, dock, inspection, acceptance, or transmittal | Evidence download | Detailed evidence for independent audit |
| Viewer | Logistics dashboard | Summary | No | No | No | No | No | No evidence download | Aggregate counts only; no shipment IDs, files, IARs, or custody parties |

## Evidence-Based Process Review

| Role | Tab / page | View | Create | Edit | Delete | Approve | Process | Export | Sensitive data |
|---|---|---:|---:|---:|---:|---:|---:|---:|---|
| Pharmacy Staff | Reviews/KPI/DPRI | No | No | No | No | No | No | No | No |
| Warehouse Staff | Reviews/KPI/DPRI | No | No | No | No | No | No | No | No |
| Inventory Manager | Review registry/detail; evidence tabs; DPRI | Yes | Review and DPRI benchmark | Draft narrative | No | No | Submit review; implement approved recommendations | Browser print where available | KPI, supplier, savings, shrinkage, bottleneck, and narrative evidence |
| Administrator | Review registry/detail; evidence tabs; DPRI | Yes | No | No | No | Yes, only another actor's submitted review | Reject/return or approve; no evidence modification | Browser print where available | Full review evidence |
| Super Administrator | All review tabs/actions | Yes | Yes | Yes | No | Yes, subject to maker/checker rule | Yes | Browser print where available | All review data |
| Auditor | Review registry/detail; evidence tabs; DPRI | Yes | No | No | No | No | No implementation or evidence change | Browser print where available | Full read-only evidence for analysis/audit |
| Viewer | Review registry/detail; evidence tabs; DPRI | Yes | No | No | No | No | No | Browser print where available | Read-only published/historical review data; no edit controls or audit trail |

## Administrative access

| Role | Users / role matrix | Audit trail |
|---|---|---|
| Pharmacy Staff | No | No |
| Warehouse Staff | No | No |
| Inventory Manager | No | No |
| Administrator | Create/update/deactivate/unlock users; view permission matrix | No |
| Super Administrator | Yes | Yes |
| Auditor | No | Read-only |
| Viewer | No | No |

## Access holes found and resolved

| Finding | Affected role(s) | Risk before fix | Resolution |
|---|---|---|---|
| Auditor held `review_supplier_compliance` and `approve_process_review` | Auditor | Could verify supplier evidence and approve/reject the process being audited | Removed every non-view permission from Auditor; direct POSTs now return 403 |
| “Register Inbound Shipment” and dock controls rendered on a read page | Auditor, Viewer | Operational shipment controls appeared in read-only panels | Buttons and modal forms now require `manage_logistics_records`; endpoint already enforces the same Gate |
| Supplier controller middleware referenced stale method names | Every role with supplier read access | Crafted requests could add/deactivate/reactivate products, add prices, or add contracts without `manage_suppliers` | Middleware now names every actual controller method; regression tests exercise direct POST bypass attempts |
| Supplier contact/upload/catalog/contract forms rendered without checks | Auditor, Viewer, Administrator | Read-only roles received mutation forms in the DOM | Each form, row action, and modal now mirrors `manage_suppliers` |
| Stock-movement API exposed update and delete | Any API user with movement permission | Historical inventory transactions could be rewritten or removed | API routes are now index/show/store only; PUT/PATCH/DELETE return 405 |
| Logistics upload/verify/supersede/IAR forms remained in DOM | Read-only logistics roles | Modified frontend could expose prohibited forms | Modal markup is permission-gated in addition to controller middleware |
| Process-review submit/edit/reject/implement forms lacked complete UI gates | Auditor, Viewer | Read-only users saw editable controls or hidden forms | Forms and modals now require their exact create/approve/implement permissions; read-only narrative replaces edit form |
| Procurement JavaScript used the wrong abilities for approve and receive buttons | Mixed operational roles | A button could be hidden from its operator or shown to a role whose request would fail | Checks now use `approve_requisition` and `receive_purchase_order`, matching endpoints |
| Requisition approver could see Cancel but controller required creator permission | Administrator approvers | Visible action returned 403 | Cancel accepts creator-or-approver permission, then service-level ownership/role checks enforce the record rule |
| Mixed read/write pages shipped create/receive/transfer/topology modals to all readers | Auditor, Viewer | Prohibited operational forms were present in page source | Requisition, receiving, transfer, warehouse-topology, and label forms now use exact abilities |
| Supplier profiles/API exposed contacts, TIN, addresses, pricing, contracts, and internal notes to Viewer | Viewer | Sensitive commercial and contact data leaked through related/detail/API views | Added `view_supplier_sensitive_data`; Viewer receives summary fields only and cannot download compliance evidence |
| Procurement/API exposed financials, quotations, commercial terms, evaluations, and internal notes too broadly | Viewer, Warehouse Staff | Read access to a parent module disclosed unnecessary commercial data | Added `view_procurement_sensitive_data`; web tables/tabs and API resources redact these fields while Auditor retains read-only evidence |
| Viewer could open detailed shipment/document/IAR/custody pages | Viewer | Operational evidence and custody identities were available beyond summary need | Added `view_logistics_sensitive_data`; Viewer is limited to aggregate dashboard metrics |
| Administrator had warehouse topology permissions without the page-view permission | Administrator | Valid configuration navigation could end in 403 | Added warehouse-task view access without granting task execution |

## Verification expectations

- Every role must be checked against its granted ability list.
- UI assertions cover module navigation, mixed read/write pages, prohibited buttons, modals, and sensitive fields.
- HTTP assertions cover direct GET/POST/PUT/DELETE and API attempts, including persistence checks after denial.
- Authorized Inventory Manager, Warehouse Staff, Pharmacy Staff, Administrator, and Super Administrator workflows remain enabled according to this matrix.
- Maker/checker and ownership rules remain service-side and are evaluated after the role permission Gate.
