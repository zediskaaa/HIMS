---
name: hims-security-auth
description: Guides security-sensitive HIMS work involving authentication, authorization, login panels, guards, roles, permissions, MFA, OTP or TOTP, passwords, lockout, account unlock, profile changes, sessions, or protected accounts. Use for implementing, modifying, debugging, or reviewing these flows and their bypass resistance.
---

# HIMS Security and Authentication

Use this skill whenever a change can affect who may authenticate, what an authenticated account may do, or how credentials and sessions are protected. Inspect the current flow before changing it; the paths below describe the verified architecture, not permission to redesign it.

## Security Invariant

**Enforce every security decision server-side.** JavaScript, Blade visibility, disabled controls, and client-side validation may improve usability, but they must never be the only control.

For every protected action, account for direct URL access, crafted HTTP requests, stale sessions, the wrong login panel, changed roles/status, cross-account identifiers (IDOR), and privilege escalation.

## Current Authentication Topology

| Panel | Guard | URL prefix | Login route | Accepted roles |
|---|---|---|---|---|
| Staff | `web` | `/` | `login` | Non-administrative roles |
| Admin | `admin` | `/admin` | `admin.login` | `UserRole::Administrator` |
| Super Admin | `super_admin` | `/super-admin` | `super-admin.login` | `UserRole::SuperAdministrator` |

All three guards use the `users` provider but maintain separate session contexts. Preserve:

- `AuthenticationPanel` for role-to-panel mapping and panel route names.
- `AuthenticationContext` for the active guard and panel-aware navigation/session behavior.
- The panel route files and `bootstrap/app.php` middleware pipeline.
- `Permission` abilities, `UserRole::permissions()`, Gates registered in `AppServiceProvider`, and controller/route middleware.
- `UserAccountService` rules for assignable roles, protected accounts, self-lockout prevention, and retention of at least one active administrator.

Do not infer access from a route prefix alone. Inspect both route and controller middleware.

## Security-Change Workflow

Inspect the authentication entry point -> trace guard and pending-session state -> verify role/status and permission enforcement -> inspect frontend behavior -> test legitimate, invalid, wrong-panel, direct-request, and stale-session paths -> verify persisted state and audit effects.

If the task is only a UI change, still confirm that the underlying server check remains intact.

## Authorization and Account Boundaries

- Authenticate with the intended guard and authorize the specific ability or management rule. Do not replace granular `Permission` checks with broad “logged in” checks.
- Re-query or otherwise validate server-owned target state for sensitive mutations. Never trust submitted role, owner, status, or protected-account flags.
- Preserve the dedicated Super Admin boundary. Ordinary administrators cannot create a Super Administrator or manage administrative/protected accounts beyond `UserAccountService` rules.
- HIMS deactivates accounts to preserve historical ownership and has no ordinary user-delete route. Do not add deletion as a shortcut.
- Account unlock is a specific `UserAccountService` operation: only a Super Administrator may unlock another eligible, temporarily locked, non-Super-Admin account. It clears the active restriction but preserves the progressive lockout count.

## MFA and OTP

HIMS supports two login challenge modes:

- Authenticator TOTP via `AuthenticatorService`, `AuthenticatorSecretService`, `AuthenticatorSetupService`, the encrypted model cast, and `LoginMfaService`.
- Email MFA when `users.mfa_enabled` is active. OTP values are stored as hashes in pending session state; verify/resend routes are throttled.

An enabled authenticator takes precedence over email MFA. Preserve `MfaSession` completion markers, pending-flow expiry, attempt limits, resend cooldowns, session regeneration, and the invalid-secret recovery path.

Rules:

- Never treat a corrupt or undecryptable authenticator secret as “MFA disabled.” Follow the explicit recovery behavior.
- Never bypass MFA by setting the completion marker from unverified input or by redirecting around middleware.
- Store long-lived authenticator secrets only through the existing encrypted cast. Pending setup state is encrypted in the session.
- Expose a setup secret/QR only through the intended authenticated setup response. Never include live or user-supplied passwords, OTPs, TOTP seeds, provisioning URIs, password hashes/fingerprints, `APP_KEY`, `.env` values, session secrets, API keys, or tokens in logs, tool output, screenshots, or final reports. Tests may use isolated synthetic credentials as necessary inputs; do not echo them in reports.
- When debugging secrets, report only safe facts such as presence, expected format, length, status, or exception class.

## Passwords and Sensitive Profile Changes

- Reuse `PasswordStandard`; do not create a weaker parallel rule. The current policy requires at least eight characters with upper-case, lower-case, numeric, and special characters.
- Route password writes through `PasswordHistoryService`. HIMS currently enforces **global** password reuse prevention using a keyed blind fingerprint plus Laravel hash verification; do not silently change it to per-user history or bypass the transaction/unique constraint.
- Password expiration is driven by `auth.password_expiration.days` and `PasswordExpirationService`. Preserve the short pending-change session and panel-specific return path.
- Use the active guard for `current_password` validation. Current-password confirmation is required for password changes, email changes, and authenticator setup/disable flows.
- Preserve reset broker tokens, OTP verification, throttling, and generic responses that resist account enumeration.
- On a password change, keep password-history recording, `password_changed_at`, remember-token/session consequences, and audit behavior consistent with the existing flow.

## Login Failure and Session Controls

- `LoginLockoutService` owns progressive login restrictions for Staff and Admin, including shadow counters for unknown identifiers to reduce enumeration/timing differences. Do not reproduce lockout logic in controllers or JavaScript.
- The Super Admin request intentionally does not use the progressive account lockout path. Preserve that explicit exception unless the user requests a policy change and the complete threat model is reviewed.
- Wrong-panel guidance is shown only after valid credentials establish the correct panel; generic authentication failures must not reveal whether an account exists or its role.
- `EnforceSessionInactivity` is authoritative. Passive polling must not extend activity, remember-me must not restore an expired session, and API/HTML responses retain different timeout behavior.
- Preserve session invalidation/token regeneration on logout or rejected panel state and session regeneration after authentication.
- Session-warning UI is advisory; expiration remains server-enforced.

## Audit and Error Handling

Use `hims-audit-logging` when adding or changing auditable security events. Only use an existing `AuditAction` when its semantics match; add an enum case and tests when a genuinely new event is required. Never put credential material in descriptions or old/new values.

On authentication, encryption, or MFA failure, inspect the real exception and state transition. Preserve fail-closed behavior. Do not log a secret, swallow integrity failures, disable MFA, loosen a policy, or reset account data merely to make the error disappear.

## Verification

Use focused tests from `tests/Feature`, especially the panel authentication, role access, MFA, lockout, password, profile, session, user-management, and audit suites.

Test proportionally, but security changes normally need:

- valid and invalid credentials/input;
- each affected guard/panel and a wrong-panel attempt;
- inactive, wrong-role, unauthorized, and direct URL/request access;
- target-account authorization and protected-account cases;
- expiry, replay/stale pending state, throttling, and attempt exhaustion where relevant;
- successful flow plus bypass attempts;
- no secret exposure and no partial state change on failure.

Use `hims-testing` for repository-specific test conventions. Do not declare the change secure from a happy-path test alone.
