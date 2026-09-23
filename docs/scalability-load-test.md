# HIMS scalability verification

Run this against an isolated, production-like deployment with representative item, movement, order, audit, and notification volumes. Do not run write scenarios against shared hospital data. Record the dataset size, application and database instance sizes, PHP worker count, queue worker count, cache/session/queue drivers, and software versions alongside every result.

## Read and login profile

`tests/load/hims-read.js` drives distinct staff sessions through login, dashboard, live counters, global search, catalogue browsing/filtering, reports, and forecasting. Set `HIMS_BASE_URL`, `HIMS_PEAK_VUS`, and `HIMS_USERS_JSON` in a secure process environment, then run `k6 run tests/load/hims-read.js`. The JSON value is an array of `{ "email": "...", "password": "..." }` objects with at least one **distinct** staff account per virtual user. Use dedicated accounts with valid, unexpired passwords and no MFA for this profile; run MFA separately with an approved test delivery channel. Do not commit credentials or print the environment.

Run a baseline at low load, then increase `HIMS_PEAK_VUS` in steps on the same dataset. Repeat each step after warm-up and again with an empty forecast cache. The script deliberately sends one session per virtual user; k6's per-VU cookie jar carries the login state. Its tagged requests separate login time from each read path. A login redirect to an MFA or password-expiry page counts as failure, not a successful dashboard session.

## Write and background profiles

Use isolated fixtures and separate load scenarios for the following. Preserve unique references and assign each virtual user its own stock item/order where measuring throughput; include a second contention phase where multiple users act on **one** item/order to verify serialization and invariants.

| Workflow | Endpoint or operation | Invariant to check after the run |
| --- | --- | --- |
| Stock movement | `POST /inventory/stock-movements` | Ledger, per-location balances, item rollup, and alerts agree; no negative issue balance. |
| Purchase order | `POST /inventory/purchases/orders`, then receive/approve routes | One receipt/approval transition per order; no duplicate stock or commitment. |
| Export | `GET /inventory/reports/generate` for `csv` and `json` | Correct row counts, audit event, and no timeout or memory spike. |
| Notifications and audit | Trigger stock/order workflows | Expected recipient count and exactly one appropriate audit event per committed action. |
| Import | `POST /inventory/import/preview`, then `/commit` with returned token | No duplicate commit; persisted counts match result and validation rules. |
| Forecast | `GET /inventory/demand-forecast`, plus explicit refresh | One queued warm-up per forecast window during a cache miss; accuracy and audit semantics unchanged. |

Write scenarios must capture CSRF tokens, use authorized roles, and account for the existing 5-minute session lifetime. MFA login needs valid test OTP/TOTP delivery; never bypass MFA or reuse a production secret for load testing. Keep import and export file sizes representative, and compare simultaneous with serial runs.

## Measurements and acceptance

For every tagged endpoint collect throughput, p50/p95/p99 response time, non-2xx rate, timeouts, and bytes transferred. Collect database query count and slow-query plans for the dominant requests, active/idle connections and lock waits, PHP worker CPU and peak RSS, database CPU and memory, queue depth/oldest-job age/runtime/failures, cache hit rate, and external AI request count/time. Check login/MFA failure rates separately from application errors. Choose acceptable latency/error/queue-age limits before testing; there is no validated concurrency capacity in this repository.

The supplied development defaults use database sessions, cache, and queues. For multiple application servers, configure shared session/cache/queue stores (typically Redis or managed equivalents), a shared durable filesystem for uploads/exports, consistent `APP_KEY` and deployment configuration, and enough queue workers for forecast/document workloads. Keep the forecast worker timeout at or above 180 seconds and the queue connection's `retry_after` above that timeout (the database and Redis defaults here are 240 seconds); an overridden `DB_QUEUE_RETRY_AFTER` or `REDIS_QUEUE_RETRY_AFTER` must respect this ordering. Run `config:cache`, `route:cache`, and `view:cache` during deployment after environment variables and routes are finalized. The built-in PHP development server and SQLite in-memory PHPUnit tests do not establish production concurrency capacity.
