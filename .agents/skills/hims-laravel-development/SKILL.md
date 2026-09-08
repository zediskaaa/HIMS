---
name: hims-laravel-development
description: Guides changes to the HIMS Laravel application, including routes, controllers, requests, services, models, enums, APIs, console commands, and backend feature work. Use for HIMS feature development, bug fixes, behavior changes, or necessary PHP refactoring; use the narrower security, database, UI, audit, testing, or Git skill as well when that domain is involved.
---

# HIMS Laravel Development

Use this skill for application-level work in HIMS. `AGENTS.md` owns general rules such as surgical changes and truthful verification; this skill adds the codebase-specific architecture and decisions needed to apply those rules.

## Verify the Current Shape

The inspected baseline is Laravel 12 on PHP 8.2+, with Blade, Alpine.js, Tailwind CSS, Vite, Eloquent, Sanctum, and PHPUnit. Treat this as a routing aid, not a substitute for inspection: dependencies and architecture can change.

Before editing, trace the requested behavior through the files that actually participate:

1. Locate the route and every route file/provider that registers it. HIMS uses `routes/web.php`, included panel-auth route files, `routes/api.php`, and `App\Providers\RouteServiceProvider`.
2. Inspect controller-level `HasMiddleware`, route middleware, Gates, requests, services, models, views/resources, and existing tests. Authorization is often declared on controllers rather than beside the route.
3. Search for an existing enum, service, component, scope, resource, or helper before adding one. Do not invent conventional Laravel files that are absent here.
4. Identify the smallest observable success condition and the important failure path.

## Choose the Change Path

- **Bug:** reproduce -> trace the real request/data path -> identify the root cause -> make the narrow fix -> rerun the reproducer and adjacent regression tests.
- **New feature:** map the existing vertical slice -> extend its validation, authorization, domain logic, persistence, response/UI, and tests -> verify the complete user path.
- **Behavior change:** find every current entry point and consumer -> preserve unrelated contracts -> update only the affected behavior and tests.
- **Refactor:** confirm a concrete need -> establish passing focused tests first -> preserve public behavior -> keep the refactor within the requested boundary. Do not refactor merely to make a feature look cleaner.

## Architecture to Preserve

### Routes, guards, and permissions

- Preserve route names, prefixes, HTTP methods, model binding, and middleware unless changing one is part of the request.
- Shared web routes accept HIMS session guards; browser calls to `/api/v1/*` can be stateful Sanctum requests. Do not replace this with a new auth pattern casually.
- Permissions are `App\Enums\Permission` abilities granted by `App\Enums\UserRole` and registered as Gates in `AppServiceProvider`. Reuse `can:` middleware or the nearby authorization pattern; a hidden control is not authorization.
- Use `AuthenticationContext` and `AuthenticationPanel` for panel-aware behavior. Authentication changes also require `hims-security-auth`.

### Validation and responses

- Follow the nearest established validation pattern. HIMS uses `FormRequest` classes for domain and user-management input, while some focused auth actions validate in their controller.
- Extend an existing request when it owns the same input contract. Add a request class when validation or authorization is reusable or substantial; do not create one solely for uniformity.
- Preserve the endpoint's response style: Blade redirect/flash behavior for web flows and API Resources or established JSON shapes for API flows.

### Domain services and data invariants

- Keep domain decisions in the existing service that owns them. Controllers should orchestrate HTTP concerns rather than duplicate service logic.
- `InventoryAutomationService` owns stock quantity mutations, movement rows, FEFO allocation, per-location/batch balances, and cached item rollups. Do not update `inventory_items.quantity_on_hand`, `reserved_quantity`, `total_value`, or stock status through an ad hoc writer.
- Reuse `StockAlertService`, `DemandForecastService`, `UserAccountService`, and `AuditLogger` for their existing responsibilities. Inspect their current contracts before calling or extending them.
- Preserve transactions and row locks around stock, account, password-history, and sequence operations. Do not split an atomic workflow across independent writes.
- Reuse backed enums and model casts for fields already modeled by `app/Enums`; do not introduce competing raw values.

## Scope Discipline by Layer

- Change an existing route only when the endpoint contract must change.
- Change schema only through `hims-database-safety` and a new forward migration when required.
- Do not replace authentication, packages, shared components, or service boundaries to deliver a local feature.
- If an adjacent change is technically necessary, state the dependency and keep it no broader than required.
- Do not add a dependency until existing Laravel/project capabilities have been checked and the dependency has a clear maintenance benefit.

## Verification and Failure Handling

Use `hims-testing` to select proportional checks. At minimum:

- Verify the exact success behavior and the most relevant invalid or unauthorized behavior.
- Run the closest existing test file or focused filter; run the full suite only when the blast radius warrants it.
- Run asset or style checks only if those files changed.
- Inspect the actual exception, response, and persisted state when a check fails. Do not suppress the exception, weaken validation/authorization, disable the feature, or delete data to make the symptom disappear.
- Report exactly what passed, failed, or could not be run.

## Related Skills

- Authentication, permissions, MFA, passwords, lockout, or sessions: `hims-security-auth`
- Schema, migrations, seeders, relationships, or data deletion: `hims-database-safety`
- Blade, components, layout, styling, or browser interaction: `hims-ui-ux`
- Audit events or audit-trail behavior: `hims-audit-logging`
- Test design or regression work: `hims-testing`
