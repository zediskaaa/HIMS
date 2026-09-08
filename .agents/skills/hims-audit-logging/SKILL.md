---
name: hims-audit-logging
description: Guides HIMS audit-trail work involving AuditLogger, AuditAction, AuditLog, user/account events, actor or target attribution, old/new value snapshots, security events, audit search, retention, and sensitive-data redaction. Use when adding, changing, reviewing, or testing auditable actions or the append-only audit interface.
---

# HIMS Audit Logging

Use this skill for audit-event design and audit-trail behavior. The audit trail is historical evidence, not ordinary application logging or a substitute for authorization.

## Current Architecture

- `App\Services\AuditLogger` is the central application writer.
- `App\Enums\AuditAction` defines allowed event types and display labels.
- `App\Models\AuditLog` casts actions and snapshots and rejects update/delete operations to remain append-only.
- `UserObserver` records selected user creation, update, password-change, and deletion events.
- `AppServiceProvider` records Laravel login/logout events.
- `LoginLockoutService` and `UserAccountService` record temporary lock and manual unlock events.
- `Admin\AuditLogController` exposes the searchable trail and suggestions only behind the `ViewAuditTrail` permission, which is currently reserved for Super Administrators.

Inspect these files and `tests/Feature/AuditTrailTest.php` before extending the system. Do not assume an event exists because it would be common in another application.

## Decide Whether to Add an Event

Requested/security-sensitive action -> check whether an existing event or observer already records it -> reuse only a semantically exact `AuditAction` -> otherwise add one intentional enum case and its label -> write once at the authoritative success boundary -> test attribution, content, authorization, and failure behavior.

Log events that materially support accountability or security review. Avoid noisy records for page views, validation failures, polling, or internal steps unless the user explicitly requires them.

## Recording Rules

- Call `AuditLogger`; do not spread direct `AuditLog::create()` calls through production code.
- Write the event only after the authoritative action succeeds, or within the same transaction when audit and domain state must commit together. A rejected/rolled-back action must not produce a success audit record.
- Use the authenticated human as `actor` when one exists. Use a null actor for a genuinely automated/system action; `AuditLogger` snapshots it as `System`.
- Set `target` to the affected model and provide a stable human-readable target name when useful.
- Distinguish the actor from the affected account. Administrative lock/unlock or user-management actions must not attribute the target as the actor.
- Keep descriptions concise, factual, and past tense. Do not claim a state transition that was not committed.
- Capture only relevant safe old/new fields. Prefer allowlists such as the one in `UserObserver`; never dump an entire request or model.
- Preserve actor/target snapshot fields so history remains understandable after later renames or relationship deletion.
- Use `config('app.timezone')` for timestamp filtering and presentation; the current audit UI labels Asia/Manila time as PHT. Preserve the application's normal timestamp conventions rather than hardcoding a competing timezone.

## Sensitive Data Prohibition

Never store or emit passwords, password hashes or fingerprints, OTP/TOTP codes, authenticator secrets, provisioning URIs, recovery material, `APP_KEY`, `.env` values, API/private tokens, session IDs/cookies, encryption keys, reset tokens, or raw authorization headers.

Also avoid unnecessary personal or clinical information. An IP address and user agent are already captured centrally from the request; do not duplicate them in descriptions or snapshots.

For a password or MFA event, record that the action occurred and who/what it affected—not the credential material. When debugging, inspect safe metadata such as presence, field name, state, length, or exception class.

## Append-Only and Retention Guarantees

- Do not add update/delete routes, UI controls, cleanup jobs, model bypasses, or raw queries that mutate existing audit rows.
- Do not “correct” a prior event in place. If correction is required, design a separate corrective event with explicit semantics.
- Do not cascade audit-history deletion from user or domain records. Preserve nullable actor relationships and snapshot identity.
- Schema/index changes must use `hims-database-safety` and retain existing history.
- Export, retention, or purge policies are not currently implied by this skill. They require an explicit user requirement and risk review.

## Read and Search Surface

- Keep audit routes protected server-side by `ViewAuditTrail`; hiding the sidebar link is only a UI affordance.
- Validate filters and autocomplete input. Preserve bounded suggestions, server-backed results, cancellation of stale browser requests, and safe escaping.
- Do not expose restricted snapshot content through search, JSON suggestions, error responses, or logs.
- Preserve actor/target distinction, action labels, filtering, ordering, pagination, and PHT display behavior when changing the page.

## Failure Handling

If audit persistence fails during a security-sensitive or accountability-critical mutation, inspect the transaction and intended consistency rule. Do not swallow the error, forge a fallback row, write secrets for debugging, or silently continue unless the existing design explicitly treats audit failure as non-blocking.

If an observer causes duplicates, find every event source and establish the authoritative boundary; do not deduplicate by deleting historical rows.

## Verification

Use `hims-testing` and the existing audit tests. Depending on the change, verify:

- exactly one event for one successful action;
- no success event for validation, authorization, or transaction failure;
- correct `AuditAction`, actor ID/snapshot, target type/ID/snapshot, and safe old/new values;
- system-versus-human attribution;
- no credential or sensitive-value leakage;
- append-only update/delete rejection and absence of mutation routes;
- Super-Admin access plus Admin, Staff, and guest rejection;
- filtering, suggestions, ordering, pagination, and time display when the read surface changes.
