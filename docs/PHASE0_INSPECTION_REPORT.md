# CHAMA WISE — Phase 0 Inspection Report

Date: 2026-11-04
Branch: `claude/chama-wise-saas-platform-jxvjv5`

## 1. Existing project structure

The repository (`Kennah2/FLEXXNET-CHAMAWISE`) was empty at the start of this session: a git
repository with no commits and no files. There is no pre-existing Laravel application, Flutter
project, documentation, or configuration to preserve. This inspection therefore establishes the
baseline rather than auditing legacy code.

## 2. Technology versions available in the build environment

| Tool | Version found | Spec target | Status |
|---|---|---|---|
| PHP | 8.3.6 (NTS, OPcache enabled) | PHP 8.3 | Match |
| Composer | 2.8.12 | — | OK |
| Node.js | 22.23.4 | — | OK (Vite-compatible) |
| npm | 10.9.9 | — | OK |
| MySQL client (`mysql` CLI) | not installed | MySQL 8 | Not installed in this container. `pdo_mysql` and `mysqli` PHP extensions ARE present, so Laravel can connect to an external MySQL 8 server. SQLite (`pdo_sqlite`, `sqlite3`) is available and used for local/test runs. |
| Flutter / Dart SDK | not installed | Flutter (Android) | Not available in this container. Not a blocker for Phase 0–4 (backend/web); required before Phase 5 (Android app) begins. |
| Outbound HTTPS to packagist.org, registry.npmjs.org | reachable (200) | — | OK, package installs work |

Relevant PHP extensions present: `curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql,
pdo_sqlite, redis, sodium, xml, zip` — sufficient for Laravel 12, file uploads, PDF/Excel export
packages, and Sanctum-based API auth.

## 3. Existing application functionality

None. Greenfield build.

## 4. Existing dependencies

None.

## 5–8. Existing database, migrations, routes, authentication

None. All of these are created fresh in Phase 1.

## 9. Hosting compatibility

Target production hosting is shared cPanel hosting (per spec Part 27). This container is a
development/CI environment, not the production host, so actual cPanel PHP/MySQL versions,
cron behavior, and queue-worker restrictions cannot be verified from here. The codebase is built
to the documented shared-hosting constraints (database queue driver, cron-triggered scheduler,
`public/` document root, no persistent workers assumed) so it is portable to a real cPanel
account without rewrites. `docs/SHARED_HOSTING_DEPLOYMENT.md` and `docs/CPANEL_CONFIGURATION.md`
will record the exact steps and must be validated against the real hosting account before
go-live.

## 10. Proposed architecture

Modular monolith, single Laravel 12 application, single MySQL database (SQLite for local/dev
and automated tests), Blade + Tailwind + Alpine web frontend served from the same backend, a
versioned REST API (`/api/v1/...`) secured with Laravel Sanctum for the Flutter Android client.
See `docs/ARCHITECTURE.md`.

## 11. Proposed database design

Normalized relational schema grouped into the domains listed in Part 29 of the brief, built out
incrementally per phase. Phase 1 introduces: `users`, `organizations` (chamas), `chama_memberships`
(status + spatie `teams`-scoped roles), `invitations`, `audit_logs`, plus spatie/laravel-permission's
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` tables
(team-scoped by `organization_id`). See `docs/DATABASE.md`.

## 12. Multi-tenancy strategy

Each chama is an `Organization` row. Membership (and therefore role) is per
`(user_id, organization_id)` via `chama_memberships`, using spatie/laravel-permission's team
feature with `organization_id` as the team key, so the same user can hold different roles in
different chamas under one account. Every chama-owned Eloquent model gets `organization_id`
with a non-nullable foreign key, a global scope that requires an explicit current-tenant context,
and policies that re-verify the authenticated user's active membership in that organization
before allowing any read/write — never trusting a tenant ID from the client alone. See
`docs/TENANCY.md`.

## 13. Financial ledger strategy

Deferred to Phase 3 per the roadmap. Will use integer minor-unit (cents) columns or `DECIMAL`
with explicit precision — never floats — double-entry journal/ledger tables, and
immutable-with-reversal semantics (no destructive edits to posted transactions). Documented in
`docs/FINANCIAL_LEDGER.md` when that phase begins.

## 14. Payment integration strategy

Deferred to Phase 4. Manual M-Pesa payment recording first (Phase 2/3), Safaricom Daraja
sandbox integration later behind a dedicated provider abstraction so the app degrades to
manual-only if Daraja is unavailable, matching Part 13.

## 15. Flutter integration strategy

Deferred to Phase 5, once the REST API is stable. The Flutter app will consume
`/api/v1/...` only — no duplicated business/financial logic on-device.

## 16. Security strategy

Laravel's built-in CSRF protection, hashed passwords (bcrypt/argon2 default), Sanctum tokens
for API/mobile auth, rate limiting on auth endpoints, tenant-scoped policies on every chama
resource, and an append-only `audit_logs` table for sensitive actions, starting in Phase 1 and
expanded every phase after. See `docs/SECURITY.md`.

## 17. Development phases

Following Part 35 of the brief exactly: Phase 0 (this report) → Phase 1 (foundation: auth,
tenancy, membership, roles, audit, dashboard shell) → Phase 2 (savings MVP) → Phase 3 (financial
integrity) → Phase 4 (payments/Daraja) → Phase 5 (Android) → Phase 6 (advanced modules) →
Phase 7 (SaaS admin) → Phase 8 (production readiness). This session executes Phase 0 and
Phase 1 only; later phases are picked up in subsequent sessions per `docs/DEVELOPMENT_PROGRESS.md`.

## 18. Potential technical risks

- Shared cPanel hosting may not allow changing the document root to `backend/public`; a
  documented workaround (symlink or `.htaccess` redirect strategy) will be needed and verified
  against the real account before launch.
- No persistent queue workers on shared hosting — all jobs must be safe to run via
  cron-triggered `schedule:run` / `queue:work --stop-when-empty`, and idempotent.
- M-Pesa Daraja callback authenticity must be verified carefully (IP/shared-secret/signature per
  Safaricom's current docs) — to be revisited in Phase 4 against the live Daraja documentation.
- Flutter/Dart SDK is not present in this container; Phase 5 will need an environment that has
  it (or will install it at that time) before Android work can be verified by actually running
  the app.
- This is an extremely large specification (effectively a multi-quarter product). Each session
  will complete a bounded slice and record exact state in `docs/DEVELOPMENT_PROGRESS.md so work
  is resumable without re-deriving context.

## 19. Files to be created (Phase 1)

`backend/` (full Laravel 12 app — composer.json, app/, routes/, database/migrations/,
resources/views/, tests/), `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/TENANCY.md`,
`docs/SECURITY.md`, `docs/DEVELOPMENT_PROGRESS.md`, `docs/MASTER_PROJECT_SPECIFICATION.md`,
root `README.md`.

## 20. Files to be modified

None pre-existed; all Phase 1 files are new.

## Conclusion

The environment is ready for Phase 1 (PHP/Composer/Node versions match the confirmed stack, and
package registries are reachable). Proceeding with Phase 1: application foundation.
