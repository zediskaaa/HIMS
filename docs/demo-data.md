# HIMS Comprehensive Demo Data Guide

This dataset is intended for local demonstrations, development, and guided testing. The comprehensive seeder refuses to run when the application environment is `production`.

## Seed command

After the schema has been migrated on a local or disposable database, run the standard seeder:

```powershell
php artisan db:seed
```

In non-production environments, `DatabaseSeeder` delegates to `ComprehensiveDemoSeeder`, which is the canonical entry point for every HIMS demo module. You can also run it directly with `php artisan db:seed --class=ComprehensiveDemoSeeder`.

The seeders persist their records in the database, preserve existing demo accounts and records when run again, and do not wipe the database. Application screens read these stored records through their normal models and queries; seed definitions are not rendered directly by the UI.

## Demo accounts

No reusable account credentials are committed to the repository. Existing users remain in the database when demo data is reseeded.

Provision a Super Administrator interactively with `php artisan hims:create-super-admin`. Optional automated provisioning reads `HIMS_SUPER_ADMIN_*`, `HIMS_OWNER_ADMIN_*`, and `HIMS_DEMO_ACCOUNTS_JSON` from the environment. Keep those values outside version control and use them only on an isolated local or disposable environment.

Administrator accounts use `/admin/login`, Super Administrators use `/super-admin/login`, and staff accounts use `/login`.

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
