# HIMS Comprehensive Demo Data Guide

This dataset is intended for local demonstrations, development, and guided testing. The comprehensive seeder refuses to run when the application environment is `production`.

## Seed command

After the schema has been migrated on a local or disposable database, run:

```powershell
php artisan db:seed --class=ComprehensiveDemoSeeder
```

The seeder is designed to preserve existing demo accounts and records when it is run again. It does not wipe the database.

## Demo accounts

Administrator accounts use the dedicated `/admin/login` page. All other accounts below use `/login`.

| Role | Email | Initial password | Panel |
|---|---|---|---|
| Administrator | `test@example.com` | `DemoAdmin1!` | `/admin/login` |
| Inventory Manager | `ana.reyes@djnrmhs.test` | `DemoInventory1!` | `/login` |
| Warehouse Staff | `ben.santos@djnrmhs.test` | `DemoWarehouse1!` | `/login` |
| Pharmacy Staff | `cely.dizon@djnrmhs.test` | `DemoPharmacy1!` | `/login` |
| Auditor | `dino.cruz@djnrmhs.test` | `DemoAuditor1!` | `/login` |
| Viewer | `ella.flores@djnrmhs.test` | `DemoViewer1!` | `/login` |

The protected Super Administrator is provisioned separately by `SuperAdminSeeder` and signs in through `/super-admin/login`. Its credential is intentionally not duplicated in this general demo guide. Change every initial password before using the accounts outside an isolated demonstration environment.

## Included sample records

- Inventory: categories, item master records, batches, stock balances, storage locations, departments, movements, transfers, adjustments, and consumption history for demand forecasting.
- Supplier management: supplier profiles and a procurement-eligible supplier produced through the accreditation workflow.
- Procurement: procurement categories, cost centers, annual budgets, purchase requests, approval chains, RFQs, supplier invitations and quotes, evaluations, purchase orders, revisions, and line items.
- Smart warehousing: warehouse topology, receiving and quarantine areas, pick faces, cold-chain telemetry, warehouse tasks, LASA items, narcotics records, serialized inventory, and surgical consignments.
- Logistics and records: shipments, goods receipts, receipt lines, inspection and acceptance reports, chain-of-custody events, and sample document metadata/files.
- Process review reference data: DPRI reference prices linked to matching inventory items.

## Suggested role walkthrough

1. Sign in as Viewer to confirm read-only navigation and reports.
2. Sign in as Pharmacy Staff to inspect stock, create requisitions, and use pharmacy-scoped workflows.
3. Sign in as Warehouse Staff to demonstrate receiving, movements, transfers, cycle counts, and warehouse tasks.
4. Sign in as Inventory Manager to demonstrate item, supplier, procurement, forecasting, and inventory operations.
5. Sign in as Auditor to inspect governance records, process reviews, and the audit trail without operational write access.
6. Sign in as Administrator to review users, access control, and administrative oversight.
