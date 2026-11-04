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

## Phase 2 — Savings MVP (contribution schedules & obligations)

**Status:** Core slice complete.

### Completed

- Full contribution domain: `contribution_schedules`, `schedule_members` (for schedules scoped to
  selected members only), `contribution_obligations` (one row per member per period), `contributions`
  (recorded payments), `contribution_allocations` (the auditable link between a verified payment
  and the obligation(s) it pays down).
- `App\Support\Money` — all money is integer minor units (cents), never a PHP float or a
  bcmath-dependent decimal string; see `docs/FINANCIAL_LEDGER.md` for why (this container has no
  `bcmath` extension, and the target shared host can't be assumed to either).
- `PeriodCalculator` — timezone-aware period/deadline math (daily/weekly/monthly; "custom" is
  accepted but doesn't auto-generate). Covered by 13 unit tests including leap-year February,
  non-leap-year February, and a December→January year boundary.
- `ObligationGenerator` — idempotent, lazy generation of obligation rows for every period that has
  begun, for every applicable member, triggered on viewing the contributions page, on verifying a
  payment, and by the new `contributions:assess` Artisan command (scheduled daily at 00:10
  Africa/Nairobi in `bootstrap/app.php`, for chamas nobody is actively viewing — see
  `docs/CPANEL_CONFIGURATION.md`). Assesses overdue status and missed-contribution penalties
  against grace-period expiry.
- `ContributionAllocator` — allocates a verified payment across a member's outstanding obligations
  oldest-period-first (FIFO); overpayment leaves an honest surplus rather than inventing a credit
  against periods that don't exist yet; `reverse()` undoes an allocation by restoring obligation
  balances and stamping (not deleting) the allocation rows.
- Manual payment recording and a separate verification step (Part 13): any member can record their
  own payment; recording on another member's behalf requires the `manage-finances` permission; a
  payment only affects balances once a `manage-finances` user verifies it (never the same action
  as recording, even for an official). Reject and reverse actions, both audit-logged.
- Web UI: `/chamas/{organization}/contributions` (statement, record form, pending-verification
  queue for officials, obligations list with penalty amounts shown separately from the base
  contribution per Part 11's "remains outstanding separately from the penalty"),
  `/contributions/schedules` (list/create/show, with a per-member expected/paid/overdue summary).
  Overview page's "Total Contributions" stat and "Record Contribution" quick action are now wired
  to real data — no longer a Phase-2 placeholder.
- `DevDataSeeder` now creates the actual "Daily savings" `ContributionSchedule` (KES 100/day, KES
  50 penalty) for the sample chama and generates its obligations, so the dev seed demonstrates the
  full feature rather than just configuration values sitting unused in JSON.

### Files created

`app/Models/{ContributionSchedule,ContributionObligation,Contribution,ContributionAllocation}.php`,
`app/Services/Contributions/{ContributionPeriod,PeriodCalculator,ObligationGenerator,ContributionAllocator}.php`,
`app/Support/Money.php`, `app/Policies/{ContributionSchedulePolicy,ContributionPolicy}.php`,
`app/Policies/Concerns/ChecksChamaPermissions.php` (shared trait — see "Files modified"),
`app/Http/Controllers/{ContributionScheduleController,ContributionController}.php`,
`app/Http/Requests/{StoreContributionScheduleRequest,StoreContributionRequest}.php`,
`app/Console/Commands/AssessContributionObligations.php`,
`database/migrations/2026_11_04_144*` (schedules, schedule_members, obligations, contributions,
allocations), `resources/views/organizations/contributions/**`,
`resources/views/components/status-badge.blade.php`, `tests/Concerns/CreatesChamas.php`,
`tests/Unit/Support/MoneyTest.php`, `tests/Unit/Services/Contributions/PeriodCalculatorTest.php`,
`tests/Feature/Contributions/{ObligationGenerationTest,ContributionAllocatorTest,ContributionControllerTest,ContributionScheduleControllerTest}.php`,
`docs/FINANCIAL_LEDGER.md`, `docs/CPANEL_CONFIGURATION.md`.

### Files modified

`app/Models/{Organization,ContributionSchedule}.php` (casts changed to `date:Y-m-d` — see Known
issues/fixed below), `app/Http/Controllers/{OrganizationController,ModulePlaceholderController}.php`,
`app/Policies/{OrganizationPolicy,ChamaMembershipPolicy,InvitationPolicy}.php` (refactored onto
the new shared `ChecksChamaPermissions` trait — no behavior change, confirmed by the full suite
staying green before and after), `routes/web.php`, `resources/views/layouts/chama.blade.php`,
`resources/views/organizations/show.blade.php`, `database/seeders/DevDataSeeder.php`,
`bootstrap/app.php` (added `withSchedule()`).

### Tests executed

`php artisan test` → **87 passed, 222 assertions**. `vendor/bin/pint --test` → clean. The full
golden path (create schedule → view generated obligations, including 10 days overdue with
penalties → record a payment → verify it → allocation reflected in the statement and the Overview
stat card) was driven through a real headless browser against `php artisan serve`, not just
asserted via HTTP status codes.

**Four real bugs were found and fixed during this work**, not merely worked around:

1. **Penalty never assessed.** `ObligationGenerator` relied on a DB column default
   (`penalty_status` defaulting to `'none'`) being reflected on the in-memory model right after
   `create()` — it isn't; Eloquent doesn't refetch defaults. The check `=== PENALTY_NONE` compared
   against `null` and silently skipped every penalty. Fixed by setting every such column
   explicitly in the insert payload instead of relying on the database to fill it in. The same
   class of bug recurred almost immediately in `ContributionScheduleController::store()` (a
   schedule's `timezone` relied on its own DB default and crashed `PeriodCalculator` with a null
   timezone the moment a schedule was created through the real HTTP form) — fixed the same way.
2. **Idempotency / uniqueness broken by date-cast format.** `period_start`'s plain `'date'` cast
   serialized to a full `Y-m-d H:i:s` string on insert, but the `firstOrCreate` lookup searched
   with a `Y-m-d`-only string, so the lookup never matched an existing row — harmless on a schedule's
   very first generation, but a `UniqueConstraintViolationException` on every regeneration after
   that (exactly what the daily cron command would hit). Fixed by casting `period_start`/
   `period_end` (and `ContributionSchedule`'s `start_date`/`end_date`) as `date:Y-m-d` so the
   stored and searched formats match.
3. **Deadlines silently shifted by the timezone offset on every database round-trip.** `due_at`/
   `grace_expires_at` were persisted as Carbon instances still carrying the schedule's own
   timezone (e.g. Africa/Nairobi); Eloquent's `datetime` cast serializes by printing whatever
   wall-clock digits the value currently holds, without first converting to the app's UTC
   timezone, so the stored string silently became "local time with no timezone marker," and the
   next read reinterpreted those same digits as UTC — a 3-hour shift, worse on each successive
   regeneration. This did not show up in any assertion against `status` or amounts (unaffected by
   display timezone), only in a real browser showing "due 05 Nov 2026 02:59" for a deadline
   configured as 23:59 the day before. Fixed by calling `->utc()` before persisting, with a new
   regression test (`test_due_at_survives_a_database_round_trip_in_the_schedules_timezone`) that
   fetches a fresh model from the database rather than reusing the in-memory object — confirmed to
   fail without the fix before being accepted.
4. **A DB-level `unique(organization_id, transaction_reference)` constraint was stricter than the
   actual business rule.** It blocked even *recording* a second pending contribution with a reused
   reference (e.g. re-entering after the first was rejected for an unrelated reason), when Part 13
   only requires that the same reference never be verified twice — which was already separately
   enforced in `ContributionController::verify()`. Relaxed the migration to a plain (non-unique)
   index and left the application-level check as the actual guard.

Also refactored the three Phase 1 policies onto a new shared `ChecksChamaPermissions` trait to
avoid a fifth near-identical copy of the tenant-membership-and-permission check; the full suite
was re-run immediately after and stayed green, confirming no behavior change.

### Known issues

- No MySQL 8 verification yet (still SQLite-only for local dev/test — a pre-existing Phase 1 gap,
  not new to this phase, but worth flagging again now that `date:Y-m-d`/datetime-cast timezone
  behavior is load-bearing: this should specifically be re-verified against real MySQL before
  launch, since date/datetime serialization details can differ by driver).
- "Custom" frequency schedules are accepted by the form but intentionally do not auto-generate
  obligations (Part 11 doesn't require it); there's no UI yet for manually creating individual
  obligations on a custom schedule.
- No schedule-editing UI — a schedule's terms are fixed once created (Part 11's "maintain history
  of important changes" amendment workflow is deferred).
- Loans, accounts, transactions, reports, accounting, and chama settings remain honest "not built
  yet" placeholder pages (unchanged from Phase 1).

### Security notes

Every new route re-verifies active membership and, where relevant, the `manage-finances`
permission via the same fresh-User-instance pattern established in Phase 1 (now shared via
`ChecksChamaPermissions`), so the cross-tenant guarantees from Phase 1 extend to the new
contribution routes — covered by dedicated tests in `ContributionControllerTest` and
`ContributionScheduleControllerTest`. Verification and recording are kept as separate actions/audit
events even when performed by the same official, preserving the verification trail Part 13 asks
for.

### Next step

Phase 3 (Financial Integrity, per Part 35): financial accounts, the double-entry ledger, journal
entries, reconciliation, withdrawals and their approval workflow, adjustments/reversals at the
ledger level, and financial reporting — this is also where the Overview page's "Accounts" /
"Transactions" / "Accounting" placeholders and the "Financial summary" card become real. Before
that, consider verifying migrations against real MySQL 8 (flagged in both Phase 1 and Phase 2 now).

---

## Addendum — username-based login

Per a user request, accounts now have a unique `username` (chosen at registration, editable on
the profile page) alongside email. The login form accepts either (`LoginRequest` detects which
one was typed and matches it against the right column). Usernames are always compared
case-insensitively, normalized in PHP (`User::normalizeUsername()`) rather than left to the
database's collation — a real, deliberately-caught risk: MySQL's default collation is
case-insensitive while SQLite's (used for local dev/test) is not, so without normalizing, login
behavior would silently differ between this environment and production. A migration backfills
a derived username for any pre-existing accounts.

Updated: `app/Models/User.php` (`loginFieldFor()`, `normalizeUsername()`),
`app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`,
`app/Http/Requests/ProfileUpdateRequest.php`, the register/login/profile Blade views (also
rebranded the previously-unstyled default Breeze form components — text inputs, buttons, labels —
to the navy/pink/teal palette while touching these pages), `database/factories/UserFactory.php`,
`database/seeders/DevDataSeeder.php` (Ken/Frank now have usernames "ken"/"frank"). New migration:
`add_username_to_users_table` — added nullable+unique rather than non-nullable, because this
environment has no `doctrine/dbal` package, which Laravel's column `->change()` requires;
"always present" is enforced at the validation layer instead, consistent with how other
DB-default-reliant issues were handled in Phase 2.

94 tests passing (up from 87 — new and updated coverage for username login, case-insensitive
matching, registration validation, and profile username updates), Pint clean, verified in a real
browser: registration, login with a mixed-case dotted username, login with email, and the seeded
sample accounts logging in by username.

---

## How to resume work in a new session

1. Read this file top to bottom — it states the actual current phase and what remains.
2. Run `cd backend && composer install && php artisan test` to confirm the existing test suite
   still passes before adding anything.
3. Check `git log` on `claude/chama-wise-saas-platform-jxvjv5` for the real commit history —
   this file is a summary, the commits are the ground truth.
4. Continue with the "Next step" noted above, or the next unstarted phase in
   `docs/MASTER_PROJECT_SPECIFICATION.md` / the original brief, Part 35.
