# CHAMA WISE — Development Progress

This file is the single source of truth for "what actually exists" in the codebase. Update it at
the end of every completed phase (or session) before handing off. Do not trust this file's
narrative over the actual code — if in doubt, inspect the code.

---

## Phase 0 — Repository & environment inspection

**Status:** Complete.
**Date:** 2026-11-04.
**Output:** `docs/PHASE0_INSPECTION_REPORT.md`.

Repository was empty (no commits, no files). PHP 8.3.6, Composer 2.8.12, Node 22.23.4 confirmed
available and matching the spec's baseline. MySQL client and Flutter/Dart SDK not present in this
container (not blockers for Phase 0–4). Packagist and npm registries reachable.

---

## Phase 1 — Application foundation

**Status:** Core slice complete (this session). Not everything Part 35 lists for Phase 1 is
done — see "Next step" below for what's deliberately deferred.

### Completed

- Laravel 12.71.0 app scaffolded in `backend/`, PHP 8.3, SQLite for local dev/tests (MySQL 8 in
  production per the spec — connection config is environment-driven, untouched).
- Laravel Breeze (Blade + Tailwind + Alpine stack) installed for authentication: registration,
  login, logout, email verification, password reset/confirm, profile edit — all Breeze's own
  feature tests pass unmodified.
- Brand applied: navy/pink/teal palette and Montserrat/Inter fonts wired into
  `tailwind.config.js` / `resources/css/app.css`; the real CHAMA WISE logo (both full lockup and
  icon, supplied by the user) copied into `public/images/brand/` and used via a new
  `<x-application-logo>` component (`variant="icon"|"full"`) across the navigation, guest layout,
  landing page and favicon — no placeholder/invented logo was used.
- Multi-tenancy foundation: `organizations` (chamas), `chama_memberships` (status:
  pending/active/suspended/resigned/removed, never deleted), `invitations`, `audit_logs`
  migrations and Eloquent models. spatie/laravel-permission installed with its **teams** feature
  enabled, team key renamed to `organization_id`, so one `User` can hold different roles in
  different chamas under a single account.
- Default per-chama roles (chairperson, treasurer, secretary, member, auditor) and a fixed
  permission catalog (`App\Support\ChamaPermissions`) — `ChamaRoleProvisioner` creates a new
  chama's own role rows (team-scoped) the moment it's created, so each chama's roles are
  independent and later customizable without touching other chamas.
- Tenant isolation enforced in `OrganizationPolicy` / `ChamaMembershipPolicy` / `InvitationPolicy`
  — every action re-verifies the authenticated user's **active** membership in the specific
  organization from the route, never trusting the route id alone (see `docs/TENANCY.md`).
- Chama creation (onboarding form, not yet a multi-step wizard), chama overview page, member
  listing, member invitation (link-based — see "Known issues"), invitation accept flow, and
  member status management (active/suspended/removed) by officials with the `manage-members`
  permission.
- Append-only `AuditLog` model + `AuditLogger` service, wired into chama creation, invitations,
  and membership status changes.
- `DevDataSeeder` seeds the spec's sample "Ken and Frank Savings Group" (local/testing
  environments only) with the sample contribution rules stored as `organizations.settings` JSON
  for Phase 2 to consume — not hardcoded into any business logic.
- Rebranded `welcome.blade.php`, Breeze's auth layouts, and navigation to CHAMA WISE.
- `routes/api.php` created with Sanctum-authenticated `/api/v1/user` as the only endpoint so far
  — the rest of Part 26's API surface is built module-by-module as each domain lands.

### Files created

Entire `backend/` Laravel application (see `git log` on this branch for the full file list);
notable additions beyond Breeze's own scaffolding:
`app/Models/{Organization,ChamaMembership,Invitation,AuditLog}.php`,
`app/Policies/{OrganizationPolicy,ChamaMembershipPolicy,InvitationPolicy}.php`,
`app/Services/{AuditLogger,ChamaRoleProvisioner}.php`, `app/Support/ChamaPermissions.php`,
`app/Http/Controllers/{OrganizationController,ChamaMembershipController,InvitationController,DashboardController}.php`,
`app/Http/Requests/{StoreOrganizationRequest,StoreInvitationRequest,UpdateMembershipStatusRequest}.php`,
`app/Http/Middleware/SetPermissionsTeamFromOrganization.php`,
`database/migrations/2026_11_04_*` (organizations, chama_memberships, invitations, audit_logs,
users additions, permission tables), `database/seeders/{PermissionSeeder,DevDataSeeder}.php`,
`resources/views/organizations/**`, `resources/views/invitations/show.blade.php`,
`public/images/brand/chama-wise-{icon,logo-full}.webp`, `tests/Feature/{OrganizationManagementTest,MembershipInvitationTest,OrganizationAccessTest}.php`.

### Files modified

`routes/web.php`, `routes/api.php` (new), `bootstrap/app.php` (api routing + `tenant` middleware
alias), `app/Models/User.php`, `app/Http/Controllers/Controller.php` (added
`AuthorizesRequests`), `config/permission.php` (teams on, team key renamed), Breeze's generated
views (`layouts/app.blade.php`, `layouts/guest.blade.php`, `layouts/navigation.blade.php`,
`dashboard.blade.php`, `welcome.blade.php`, `components/application-logo.blade.php`),
`tailwind.config.js`, `resources/css/app.css`, `.env` / `.env.example` (`APP_NAME`).

### Database / migrations

All migrations run cleanly on SQLite from empty (`php artisan migrate:fresh`); see
`docs/DATABASE.md` for the schema. No migration has been tested against MySQL 8 yet — only SQLite
(local dev/test). This should be verified against a real MySQL 8 target before Phase 8.

### Tests executed

`php artisan test` → **37 passed, 87 assertions** (25 are Breeze's own auth/profile tests,
unmodified; 12 are new: chama creation/authorization, invitation send/accept, and — most
importantly — 5 cross-tenant access-denial tests in `OrganizationAccessTest`). `vendor/bin/pint
--test` → passed, no style violations. The golden path (register → login → create chama → view
chama → invite a member → see the invite link → member list shows both members) was also driven
through a real headless browser (Playwright) against `php artisan serve`, with screenshots
confirming the branding and data render correctly — not just the HTTP status codes.

**A real bug was found and fixed during this testing**, not merely worked around: a feature test
asserting "the same user has different roles in different chamas" initially failed because
spatie/laravel-permission's `hasRole()` caches the loaded `roles` Eloquent relation on the `User`
model instance and does not reload it when the permissions "team" (organization) context changes
mid-request. All three policies now re-fetch a fresh `User` instance
(`User::find($user->id)->can(...)`) for the actual permission check after confirming active
membership, which eliminates the stale-cache cross-tenant leak. This is documented inline in each
policy's `hasPermission()` method.

### Known issues

- Invitation "sending" does not actually send an email — Part 20's notification channels
  (email/SMS/WhatsApp) are a later phase, so the invite link is surfaced directly to the inviter
  to copy/share instead of silently claiming a message was delivered when none was.
- Chama creation is a single form, not the full multi-step onboarding wizard Part 8 describes;
  functionally complete, UX refinement deferred.
- No MySQL 8 verification yet (see above).
- Public marketing site (Part 23: features/pricing/FAQ/etc. pages) is not built — only a single
  branded landing page exists so far.
- Platform administration (Part 22), subscriptions, billing: not started (`is_platform_admin`
  column exists on `users` but nothing uses it yet).

### Security notes

CSRF, password hashing, email verification, and rate-limited auth endpoints all come from
Laravel/Breeze defaults and are active. Tenant isolation is enforced via policies re-checking
active membership + the fresh-instance permission check described above, and covered by
dedicated cross-tenant denial tests. Invitation tokens are stored hashed (SHA-256), never in
plaintext, and never logged. No sensitive values appear in `AuditLog` entries written so far.

### Next step

Phase 2 (Savings MVP, per Part 35): contribution schedules, daily/weekly/monthly contribution
obligations, manual payment recording, missed-contribution/penalty logic (Africa/Nairobi
timezone-aware deadlines), member statements. Before that, consider: verifying migrations against
real MySQL 8, and building out the multi-step chama onboarding wizard if prioritized over Phase 2.

### Addendum — dashboard redesign and branding update

Per user-supplied reference mockups, the authenticated chama experience was redesigned from a
top-nav shell to a sidebar-based one (`App\View\Components\ChamaLayout`, `layouts/chama.blade.php`):
logo + "current chama" context, module navigation (Overview, Members, Contributions, Loans,
Accounts, Transactions, Reports, Accounting, Settings), a welcome banner, stat cards, quick
actions, and an Alpine-driven collapsible sidebar. `organizations/show.blade.php` and
`organizations/members/index.blade.php` now use it.

Sidebar items for modules this phase hasn't built (everything except Overview and Members) route
to `ModulePlaceholderController` → `organizations/coming-soon.blade.php`, an honest "not built
yet, here's the phase that builds it" page — never a 404 or a fabricated populated screen with
invented numbers. Stat cards on Overview follow the same rule: Total Members and Pending
Invitations show real data; Active Loans / Total Contributions / Accounts show a "Coming in
Phase N" label instead of a fake zero; the financial-summary card explicitly declines to show a
balance ahead of Phase 3's ledger.

The final logo files supplied by the user (icon PNG, full lockup with tagline) replaced the
earlier placeholder logo assets. The public landing page was rebuilt with a fuller SaaS layout
(hero, trust strip, feature grid, how-it-works, CTA, footer) in the same navy/pink/teal brand.

New test: `ModulePlaceholderTest` (member access, cross-tenant denial, unknown-module 404). Total
now 40 tests passing, Pint clean, verified in a real browser including the collapse interaction.

---

## How to resume work in a new session

1. Read this file top to bottom — it states the actual current phase and what remains.
2. Run `cd backend && composer install && php artisan test` to confirm the existing test suite
   still passes before adding anything.
3. Check `git log` on `claude/chama-wise-saas-platform-jxvjv5` for the real commit history —
   this file is a summary, the commits are the ground truth.
4. Continue with the "Next step" noted above, or the next unstarted phase in
   `docs/MASTER_PROJECT_SPECIFICATION.md` / the original brief, Part 35.
