---
name: hims-testing
description: Guides HIMS test creation, bug reproduction, regression coverage, and proportional verification with Laravel PHPUnit feature or unit tests. Use when adding or changing tests, reproducing defects, validating authentication or authorization, checking database behavior, or deciding the appropriate regression scope.
---

# HIMS Testing

Use this skill when test design is part of the task or when a risky change needs repository-specific verification. Domain skills define what must remain safe; this skill defines how HIMS proves it.

## Test Environment and Conventions

- HIMS uses PHPUnit through Laravel's test runner, with class-based tests under `tests/Feature` and `tests/Unit`.
- `phpunit.xml` selects SQLite `:memory:`, array cache/session, sync queues, and the array mailer. Keep tests isolated from the active `.env` database and external delivery systems.
- Most stateful feature suites use `RefreshDatabase`.
- `Tests\TestCase::actingAs()` maps a `User` to the correct `web`, `admin`, or `super_admin` guard. Pass an explicit guard only when the test intentionally exercises a mismatched or specific panel boundary.
- `UserFactory` defaults to the least-privileged `Viewer`; choose an explicit role state when a test needs authority.
- Existing factories, setup helpers, enums, and service entry points are preferred to hand-built inconsistent records.

## Choose the Test Strategy

- **Bug fix:** first add or identify a test that fails for the reported behavior; prove the failure is meaningful; fix the root cause; rerun the reproducer and the nearest regression group.
- **Feature:** test the externally observable success path plus the most important validation, authorization, and persistence failures.
- **Refactor:** run focused tests before and after; add coverage only for an uncovered behavior that the refactor could realistically break.
- **Trivial copy/style change:** verify the rendered route or built asset. Do not create brittle tests solely to match incidental markup.
- **Migration/data change:** use the isolated database, assert schema or durable outcomes, and verify representative existing data remains intact. Follow `hims-database-safety` for engine-specific checks.

Prefer behavior assertions over implementation-detail assertions. Assert status/redirect, validation errors, authorization, response payload or visible contract, database state, emitted side effects, and absence of partial writes.

## Domain Coverage

### Authentication and authorization

Exercise the affected panel and guard, correct and incorrect credentials/input, inactive/wrong-role accounts, direct URL or crafted requests, unauthorized target IDs, expired/stale state, and throttling where relevant. Existing coverage includes panel authentication, role access, MFA, lockout, password expiration/history/reset, profile changes, and session management.

Do not rely on a hidden button assertion as proof of authorization. Assert the server response and unchanged state.

### Inventory and database behavior

Create coherent item, batch, location, and stock-level records. For stock changes, assert the movement ledger, per-location/batch balance, cached item totals, alerts, and transaction rollback on failure as applicable. Reuse `InventoryAutomationService` entry points rather than fabricating a state the application cannot normally produce.

### UI behavior

Feature tests can verify routes, views, component output, validation bags, and interaction hooks. They do not execute a real browser. When responsive layout, focus, JavaScript timing, modal behavior, loading, or duplicate submission changes, also perform an appropriate browser/manual interaction check if available.

### Audit behavior

Assert the enum action, actor and target snapshots, old/new safe fields, authorization to view the trail, append-only behavior, and absence of secrets. Use `hims-audit-logging` for event semantics.

## Running Tests Proportionally

Start narrow and broaden only for a reason:

```text
php artisan test tests/Feature/RelevantTest.php
php artisan test --filter=test_specific_behavior
php artisan test
```

- Use a test-file path for a coherent feature suite and `--filter` for a focused iteration.
- Run the full suite for cross-cutting changes, shared middleware/services, schema changes with broad impact, or before completing a substantial feature when feasible.
- Build frontend assets with `npm run build` when JavaScript, CSS, Tailwind, or Vite inputs change; this is not a substitute for behavioral tests.
- Run formatting/static checks only on the scope they can meaningfully validate.

## Reliable Tests

- Control time for expiration, cooldown, and session scenarios; restore it through the framework test lifecycle.
- Avoid real sleeps, network calls, production credentials, and shared-database dependencies.
- Keep assertions deterministic. Do not depend on unordered query results, wall-clock races, or generated values without controlling them.
- Test one coherent behavior per method while allowing all necessary assertions for its state transition.
- Do not weaken production code or bypass middleware globally merely to make setup easier.
- Do not copy live secrets, OTPs, hashes, keys, or personal data into fixtures or failure output. Generate isolated synthetic credentials when a test needs credential input, and do not echo them in reports.

## When a Test Fails

Read the first relevant failure, response body, exception, and persisted state. Decide whether the implementation is wrong, the test expectation is stale, or setup created an impossible state. Fix the root cause; do not remove the assertion, catch the exception, disable the feature, reset non-test data, or broaden retries without evidence.

Report the exact commands run and their results. Distinguish a passing focused suite from an unrun full suite or unperformed browser check.
