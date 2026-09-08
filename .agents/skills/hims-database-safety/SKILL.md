---
name: hims-database-safety
description: Guides safe HIMS database work involving migrations, schema, tables, columns, indexes, foreign keys, Eloquent relationships, seeders, data backfills, deletion, or database Artisan commands. Use before any change or command that could affect persisted data, especially shared MySQL or TiDB environments.
---

# HIMS Database Safety

Use this skill for persistence changes and database-affecting commands. HIMS contains inventory history, account ownership, password history, and append-only audit records; preserving data and historical accountability is the primary constraint.

## Establish the Actual Environment

The repository is configured to support MySQL/TiDB over TLS, while `phpunit.xml` uses SQLite `:memory:` for tests. Do not assume the active `.env` points to local, disposable, shared, staging, or production data.

Before a database command:

1. Identify the selected connection and environment using configuration metadata only; do not print credentials, URLs, passwords, CA contents, or other secrets.
2. Decide whether the command is read-only, additive, data-transforming, or destructive.
3. If the target is unknown or shared and the command can mutate data, stop and obtain explicit authorization for that exact operation.

`php artisan db:check` and `php artisan migrate:status` are read-only diagnostics, but run them only when their output is needed. They do not authorize a later migration.

## Non-Destructive Defaults

- Never run `migrate:fresh`, `migrate:reset`, database wipes, `DROP`, `TRUNCATE`, bulk deletion, or equivalent destructive operations against non-isolated data unless the user explicitly requests the exact operation after the risk is identified.
- Never delete application data or modify existing production-like records merely to make a migration or test pass.
- Do not edit an already-applied migration to change deployed schema. Add a forward migration unless the user has confirmed the migration has never left an isolated development state.
- Do not run migrations against a shared/cloud connection as an automatic verification step.
- Use transactions where supported for multi-row data changes, while recognizing that DDL transaction behavior differs by database engine.

## Schema-Change Workflow

Inspect schema history and models -> identify relationships, indexes, casts, and writers -> classify the data and deployment risk -> design the smallest forward migration/backfill -> test in the isolated test database -> verify MySQL/TiDB compatibility when relevant -> run on a non-isolated target only with explicit authorization.

### Inspect before designing

- Read every migration that creates or alters the target table, not just the latest one.
- Inspect the model, relationships, casts, factories/seeders, services, validation, queries, and tests that use the field.
- Search for an existing column, index, constraint, or in-progress migration before creating another.
- Treat model definitions as application intent and migrations as schema history; neither alone proves the live schema.

### Design rules

- Preserve existing column types and precision unless the requirement demands a change. HIMS currently uses integer stock quantities and mostly `decimal(12, 2)` monetary values; verify the exact target instead of applying a generic precision rule.
- Preserve names and semantics used by Eloquent relationships and API/view contracts.
- Choose foreign-key delete behavior from the domain relationship and existing history requirements. Do not mechanically use cascade, restrict, or null-on-delete.
- Add indexes for demonstrated query/constraint needs, including uniqueness and common composite lookups; avoid speculative indexes.
- Make `down()` honest and safe. If rollback cannot restore transformed or deleted data, do not imply reversibility; define a forward recovery approach and surface the limitation.
- Do not add `Schema::hasColumn` guards by default. Use conditional DDL only when a known multi-state deployment requires it; silent guards can conceal schema drift.
- For a backfill, plan nullability/default transitions, batching, lock duration, concurrent writes, retry behavior, and how partial failure is detected.

## HIMS Historical Invariants

- `InventoryAutomationService` owns stock movements and synchronizes `item_stock_levels` with cached totals on `inventory_items`. A migration or backfill must not create contradictory balances or bypass the movement ledger.
- Stock movements and related user references provide operational accountability. Check existing foreign keys before changing deletion behavior.
- User accounts are normally deactivated, not deleted, so historical stock and audit ownership remains attributable.
- `audit_logs` are append-only at the model layer and retain actor/target snapshots even if a related user later disappears. Never repurpose, overwrite, or purge them as routine cleanup.
- Password-history rows and their blind fingerprints are security records; do not expose or casually rebuild them.

## Deactivation, Soft Deletion, and Permanent Deletion

When a request says “delete” or “remove,” determine the intended lifecycle:

- **Deactivate:** retain the row and history but block future use. This is the established account behavior.
- **Soft delete:** retain recoverable data behind a deletion timestamp. No current model should be assumed to support this without inspection and a migration.
- **Permanent delete:** removes the row and may cascade, null references, or destroy accountability.

If the intended choice is materially ambiguous, ask before implementing. For permanent deletion, identify affected rows, constraints, recovery/backup expectations, and irreversible consequences before execution.

## Verification and Failure Handling

- Run the relevant migration-backed feature tests in the configured SQLite in-memory environment.
- Verify the resulting columns, indexes, foreign keys, casts, relationships, and preservation of representative existing data.
- SQLite success does not prove MySQL/TiDB DDL compatibility. For engine-specific changes, use a dedicated non-shared MySQL/TiDB test target or safe SQL inspection; do not experiment on shared data.
- Test both migration and rollback only when rollback is intended to be safe and meaningful.
- After a failure, capture the exact error and determine whether schema, data, engine compatibility, ordering, or connectivity caused it. Do not reset the database, drop the table, suppress the exception, or delete conflicting records as a shortcut.
- Use `hims-testing` for test scope and `hims-audit-logging` when audit schema or retention is involved.
