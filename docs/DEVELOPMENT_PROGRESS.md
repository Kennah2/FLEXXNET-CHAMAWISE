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

## Phase 3 — Financial Integrity (ledger, accounts, withdrawals, expenses)

**Status:** Core slice complete.

### Completed

- Full double-entry ledger domain: `financial_accounts` (real-world cash/bank/mobile-money
  places), `ledger_accounts` (chart of accounts, asset/liability/income/expense), `journal_entries`
  (balanced transactions), `ledger_postings` (individual debit/credit lines, with an optional
  `user_id` for per-member sub-ledgers).
- `LedgerPoster` — the single code path allowed to write ledger rows. Enforces the debits-equal-credits
  invariant, that every line is a debit XOR a credit, and that every account belongs to the posting
  organization, all inside a DB transaction; rejects and persists nothing on any violation.
  `reverse()` posts a mirror-image entry rather than editing or deleting — the append-only rule
  Phase 2 established for contributions now applies to every financial record.
- `LedgerProvisioner` — idempotently provisions the three system accounts (`member_savings`,
  `penalty_income`, `general_expense`) per organization and a backing ledger account for every
  `FinancialAccount` a chama creates.
- Financial accounts: create (with an opening balance), list, per-account statement (all postings
  affecting it), and reconciliation against a bank/till statement (records the variance, never
  auto-corrects it).
- Withdrawals (Part 18): a member requests against their own savings balance only (rejected at
  422 if the request exceeds it); approval requires N distinct officials with `manage-finances`
  (default 2, configurable per org via `settings['withdrawals']['required_approvals']`, no settings
  UI yet); **self-approval is a hard policy block, not merely a permission gap** — see Known
  issues/fixed #2 below; a single rejection vetoes the request; payment is a separate action that
  re-validates the member's current balance (which can have moved since approval) before posting;
  reversal restores both the member's savings balance and the paying account's balance.
- Expenses: single-step record-and-post (debit `general_expense`, credit the paying account),
  requires `manage-finances`, reversible.
- Transactions page: org-wide chronological journal, each entry showing its full set of
  debit/credit lines with reversed/reversal badges.
- Accounting page: chart of accounts grouped by type with live balances, plus a system-wide
  debits-equal-credits sanity check (if it ever fails, something bypassed `LedgerPoster`, which
  should be structurally impossible — the check exists as a trust-but-verify display, not because
  it's expected to trigger).
- Phase 2 integration: verifying a contribution now requires choosing a `financial_account_id` and
  posts a real ledger entry (debit the chosen account, credit `member_savings` under the
  contributor's `user_id`); reversing a verified contribution reverses its linked journal entry
  too. The contribution's `financial_account_id`/`journal_entry_id` make this traceable directly
  from the payment record.
- Overview page's "Accounts" / "Transactions" / "Accounting" sidebar links and the "Withdrawal
  Approvals" pending-action card, "Accounts" stat, and "Financial summary" card are now real —
  no longer Phase-1/2 placeholders. `ModulePlaceholderController`'s remaining honest placeholders
  are `loans` (Phase 6), `reports`, `settings`.
- `DevDataSeeder` now provisions the system ledger accounts, creates an "M-Pesa Till"
  `FinancialAccount`, and verifies Ken's oldest contribution obligation through the real
  `LedgerPoster`/`ContributionAllocator` path, so the seed demonstrates an actual posted ledger
  entry and non-zero balances rather than empty accounting pages.

### Files created

`app/Models/{FinancialAccount,LedgerAccount,JournalEntry,LedgerPosting,WithdrawalRequest,WithdrawalApproval,Expense,AccountReconciliation}.php`,
`app/Services/Ledger/{LedgerPoster,LedgerProvisioner}.php`,
`app/Policies/{FinancialAccountPolicy,WithdrawalRequestPolicy,ExpensePolicy}.php`,
`app/Http/Requests/{StoreFinancialAccountRequest,StoreWithdrawalRequestRequest,StoreExpenseRequest,StoreReconciliationRequest}.php`,
`app/Http/Controllers/{FinancialAccountController,WithdrawalController,ExpenseController,JournalEntryController,AccountingController}.php`,
`database/migrations/2026_11_06_0559*` (financial_accounts, ledger_accounts, journal_entries,
ledger_postings, withdrawal_requests, withdrawal_approvals, expenses, account_reconciliations, and
the contributions ledger-links migration),
`resources/views/organizations/{accounts,withdrawals,expenses,transactions,accounting}/**`,
`tests/Feature/Ledger/{LedgerPosterTest,FinancialAccountControllerTest,WithdrawalControllerTest,ExpenseControllerTest}.php`.

### Files modified

`app/Models/{Contribution,Organization}.php` (ledger relations;
`Organization::memberSavingsAccount()`), `app/Http/Controllers/ContributionController.php`
(verify/reverse now go through `LedgerPoster`), `app/Http/Controllers/OrganizationController.php`
(provisions system accounts on chama creation; Overview page now computes real financial figures),
`app/Http/Controllers/ModulePlaceholderController.php` (accounts/transactions/accounting removed
from the placeholder list), `routes/web.php`, `resources/views/layouts/chama.blade.php` (new
"Withdrawals" nav item), `resources/views/organizations/show.blade.php`,
`database/seeders/DevDataSeeder.php`, `docs/FINANCIAL_LEDGER.md`, `docs/DATABASE.md`.

### Tests executed

`php artisan test` → **122 passed, 319 assertions**. `vendor/bin/pint --test` → clean. The full
golden path was driven through a real headless browser against `php artisan serve`: Overview's
updated stat cards and financial summary, creating a financial account, its statement view,
recording a reconciliation (confirmed "Balanced" display), recording and reversing an expense, the
org-wide transaction journal, the chart-of-accounts/accounting page, and requesting a withdrawal
(confirmed the UI correctly blocks the requester from approving their own request, showing "Can't
approve your own request" rather than an approve control).

**One real design bug was caught during this work**, before any test was even run, by reviewing my
own first draft of the withdrawals view rather than by a failing assertion:

1. **The "Pay" action only ever appeared in a member's own "my requests" table.** That meant an
   official could only pay out a withdrawal *they themselves* had requested — never another
   member's approved request — which would have made the pay step nearly unusable for a treasurer
   paying out the group's actual members. Fixed by adding a dedicated, org-wide "Approved — ready
   to pay" section (visible to any `manage-finances` holder, not scoped to the viewer's own
   requests) backed by a new `readyToPay` query in `WithdrawalController::index()`, and removing
   the now-redundant Pay button from the narrower "my requests" table.

Three smaller issues were also fixed along the way, not left as "known issues" since they were
real defects, not deferred scope:

2. A controller typo (`approve()` referenced an undefined `$request` in its `DB::transaction`
   closure — leftover from refactoring) and an unused `LedgerAccount` import, both caught by
   static review before the test suite ran.
3. The Expenses link on the Accounting page was gated behind `@can('update', $organization)`
   (settings-management authorization) rather than the correct, looser viewAny check the Expenses
   index page itself actually enforces — a real authorization-logic mismatch between a link's
   visibility and its destination's actual requirement, not a security hole (the destination page
   enforced its own correct check either way), but worth fixing so the UI doesn't lie about who can
   use a feature. Removed the incorrect gate.
4. Updating `ContributionController::verify()` to require `financial_account_id` was a deliberate,
   expected breaking change to that endpoint's contract — it broke three pre-existing Phase 2 tests
   that didn't send the new required field. Fixed by adding a `cashAccount()` test helper and
   updating the affected calls, while also strengthening those tests with new assertions on the
   resulting ledger state (balances, `journal_entry_id`) rather than just making them pass again.

### Known issues

- No MySQL 8 verification yet — still SQLite-only for local dev/test. This is a pre-existing gap
  carried forward from Phase 1/2, but it now matters more: ledger correctness depends on exact
  integer arithmetic and transaction behavior that should be re-confirmed against the real target
  driver before launch.
- `required_approvals` for withdrawals has no settings UI — it's read from
  `organization.setting('withdrawals.required_approvals', 2)` and can currently only be changed
  directly in the database. A chama settings page (already a known placeholder) should expose this.
- `penalty_income` is provisioned but nothing posts to it yet — penalty collection (assessed in
  Phase 2's `ObligationGenerator`) isn't yet wired into the ledger. A chama's penalty income is
  therefore not visible in the Accounting page even though penalties are tracked and shown on
  obligations.
- No financial reporting views yet (Part 35's "financial reporting" — income statement, balance
  sheet style summaries) beyond the raw chart-of-accounts and transaction journal; the Overview
  page's "Reports" placeholder remains honest.
- Loans, reports, and chama settings remain honest "not built yet" placeholder pages (unchanged
  from Phase 1/2, now the only three left).

### Security notes

Every new financial route re-verifies active membership and, where relevant, `manage-finances` via
the same fresh-User-instance / `ChecksChamaPermissions` pattern as Phases 1–2, with cross-tenant
denial covered by dedicated tests on every new policy (`FinancialAccountControllerTest`,
`WithdrawalControllerTest`, `ExpenseControllerTest`). Withdrawal self-approval is blocked at the
policy layer unconditionally — it does not depend on and cannot be loosened by any permission or
setting, matching Part 18's explicit requirement that no official can approve their own payout.

### Next step

Phase 4 per the roadmap (or whichever phase the user directs next): consider loan management
(Phase 6 per Part 35, already referenced by Overview's "Loan Applications" placeholder) or
financial reporting depth (income statement / balance sheet views building on this phase's ledger).
Before either, verifying migrations against real MySQL 8 remains the standing recommendation from
Phases 1–3.

---

## Phase 4 — Payments / Daraja (M-Pesa STK Push)

**Status:** Core slice complete, structurally — never tested against a live Daraja sandbox (see
Known issues).

### A decision made before writing any code: per-chama credentials, not a shared platform till

The obvious-looking shortcut — one Safaricom Daraja app for the whole CHAMA WISE platform, with
every chama's contributions flowing through it — was considered and rejected before any code was
written. It would mean CHAMA WISE itself pooling every chama's member funds in one place, which
is exactly what `docs/SECURITY.md`'s custody limitation (Part 28) says needs a separate legal/
regulatory/financial-architecture review this project has not had. Instead, each chama configures
its **own** Daraja app credentials (`payment_gateway_credentials`, one row per organization,
encrypted at rest) and money is paid straight into that chama's own Safaricom shortcode — CHAMA
WISE only asks Safaricom to send the STK Push prompt and records what it reports back. This is
recorded here, not just in SECURITY.md, because it shaped essentially every other decision in
this phase (no platform-wide `.env` Daraja secrets, a settings page instead, a callback token per
chama rather than one shared secret).

### Completed

- `PaymentGatewayCredential` — per-chama Daraja app config. `consumer_key`/`consumer_secret`/
  `passkey` encrypted via Eloquent's `encrypted` cast and marked `$hidden`; `shortcode` (not
  secret) and a `financial_account_id` choosing which of the chama's accounts receives confirmed
  payments; a random, unique `callback_token` generated once per chama, standing in for the
  payload signature Safaricom's public Daraja API doesn't provide.
- A minimal M-Pesa settings page (`organizations.payments.daraja.{edit,update}`, reached from a
  new "M-Pesa settings" quick link on the Accounting page) — the first real settings UI in the
  app (everything before this was either fixed by migration or had no UI at all). Deliberately
  scoped to only what Daraja needs, not a general chama-settings page (still a placeholder).
  Secrets are never re-displayed once saved: the form shows a masked placeholder, and leaving a
  secret field blank on update means "keep the current value," never "clear it."
- `DarajaClient` — thin HTTP wrapper for Safaricom's OAuth token exchange (cached ~55 min) and
  STK Push initiation. Every call is wrapped so a transport-level failure becomes a
  `DarajaRequestException` rather than an uncaught exception — see the real bug this caught,
  below.
- `StkPushService::initiate()` — creates a `PaymentTransaction` row before ever calling
  Safaricom, so every attempt is recorded even if the outbound call itself fails; normalizes the
  member's phone number to Safaricom's `2547.../2541...` format regardless of how it was typed in
  (`App\Support\PhoneNumber`); records Daraja's own rejection reason if the API declines the
  request outright.
- `DarajaCallbackHandler` — the only code path allowed to turn a `PaymentTransaction` into a
  verified `Contribution` and post the ledger, and only in reaction to Safaricom's own callback
  reporting `ResultCode: 0` (Part 36: an STK Push *request* is never treated as a completed
  payment — see `docs/FINANCIAL_LEDGER.md`'s Phase 4 section for the full reasoning, including
  why a confirmed STK payment is auto-verified while a manually recorded one still needs a human
  official, and why the callback's reported amount is cross-checked against cents arithmetic
  without ever using float math). Idempotent against Safaricom retrying a callback: a transaction
  already out of its initial `initiated`/`pending` state is left untouched on a repeat delivery.
- `DarajaCallbackController` (`routes/api.php`, unauthenticated by necessity) — authenticates a
  callback via the unguessable per-chama `{token}` path segment (`hash_equals()`), always
  acknowledges Safaricom with HTTP 200 regardless of outcome (to avoid a retry storm for a
  request correctly recognized as invalid), and is the one place in the whole app allowed to
  create a contribution for a user with no authenticated session.
- "Pay with M-Pesa" panel on the contributions page, shown only when a chama has Daraja
  configured and enabled — an Alpine.js component that submits, then polls a `status` endpoint
  every 3 seconds (capped at 30 attempts / 90 seconds) until the transaction reaches a terminal
  state, showing "Check your phone…" and then either "Payment received!" (reloading the page) or
  the actual failure/cancellation reason.
- `InitiateStkPushRequest` requires a **whole-shilling** amount — Daraja's STK Push API does not
  accept fractional amounts, unlike manual recording. Enforced by validation, not by silently
  truncating a member-entered fractional amount.

### Files created

`app/Models/{PaymentGatewayCredential,PaymentTransaction}.php`,
`app/Services/Payments/{DarajaClient,StkPushService,DarajaCallbackHandler}.php`,
`app/Exceptions/Payments/{DarajaNotConfiguredException,DarajaRequestException}.php`,
`app/Support/PhoneNumber.php`,
`app/Policies/{PaymentGatewayCredentialPolicy,PaymentTransactionPolicy}.php`,
`app/Http/Requests/{StoreDarajaCredentialRequest,InitiateStkPushRequest}.php`,
`app/Http/Controllers/{PaymentGatewayCredentialController,StkPushController}.php`,
`app/Http/Controllers/Api/DarajaCallbackController.php`,
`database/migrations/2026_11_06_144945_create_payment_gateway_credentials_table.php`,
`database/migrations/2026_11_06_144946_create_payment_transactions_table.php`,
`resources/views/organizations/payments/daraja.blade.php`,
`tests/Feature/Payments/{DarajaCredentialControllerTest,StkPushInitiationTest,DarajaCallbackHandlerTest}.php`.

### Files modified

`app/Models/{Contribution,Organization}.php` (`METHOD_MPESA_STK`, `paymentMethodLabel()`,
`darajaCredential()`, `paymentTransactions()`), `app/Http/Controllers/ContributionController.php`
(`$mpesaEnabled` passed to the view), `routes/web.php`, `routes/api.php` (the unauthenticated
callback route, outside every other middleware group), `resources/views/organizations/
contributions/index.blade.php` (the "Pay with M-Pesa" panel),
`resources/views/organizations/accounting/index.blade.php` (the "M-Pesa settings" quick link),
`docs/FINANCIAL_LEDGER.md`, `docs/DATABASE.md`, `docs/SECURITY.md`.

### Tests executed

`php artisan test` → **145 passed, 395 assertions** (up from 122). `vendor/bin/pint --test` →
clean. Daraja's own API is mocked with `Http::fake()` in automated tests (standard practice for
testing an HTTP integration — not the "fabricate M-Pesa success" Part 36 forbids, since these
tests verify *this app's* handling of a given Daraja response/callback, never assert a real
payment happened). The full non-network UI path was driven through a real headless browser
against `php artisan serve`: the M-Pesa settings page's empty state, saving sandbox-shaped (but
fake) credentials, reloading to confirm secrets come back masked rather than re-displayed and the
enabled checkbox persisted, the "Pay with M-Pesa" panel correctly absent before configuring and
present after, and — because this container has no route to Safaricom's sandbox — actually
clicking "Pay with M-Pesa" and watching it fail gracefully with "Could not reach Safaricom: HTTP
request returned status code 403" instead of crashing, which is exactly the real bug described
next, confirmed fixed in a live browser rather than only in a mocked test.

**One real bug was found and fixed, caught by actually trying the feature rather than by any
automated test**: `DarajaClient`'s HTTP calls had no handling for a transport-level failure (DNS
resolution, connection refused, or — as this sandboxed development container actually
demonstrated, having no network route to Safaricom at all — a request rejected outright). Only a
well-formed Daraja response with a bad status code was converted into the app's own
`DarajaRequestException`; an exception instead of a response would have bubbled up uncaught as a
generic 500, defeating the entire point of `StkPushController`'s friendly "couldn't reach M-Pesa,
try again or record manually" handling. Fixed by wrapping every outbound call in a `request()`
helper that catches `ConnectionException`/`RequestException`/any other `Throwable` and converts it
to a `DarajaRequestException` uniformly, with a dedicated regression test
(`test_a_transport_failure_reaching_daraja_fails_gracefully_instead_of_a_500`) simulating the
failure via `Http::fake()` with a throwing callback, then confirmed again live in the browser.

### Known issues

- **Never tested against a live Daraja sandbox.** This development container has no credentials
  for a real Safaricom Daraja app and no network route to `sandbox.safaricom.co.ke` (confirmed
  directly — see the bug above), and a live STK Push round trip also needs a callback URL
  Safaricom's servers can reach, which this disposable container cannot offer either. Everything
  above is verified as thoroughly as this environment allows — correctness of the app's own logic
  via mocked-HTTP tests, and the full UI/error-handling path in a real browser — but the actual
  request/response shape of a real Daraja sandbox call has not been confirmed against this code.
  **This should be the first thing verified, with real sandbox credentials, before relying on
  this feature with a real chama.**
- No UI for a member to initiate an STK Push on another member's behalf (an official paying for
  someone else still has to use manual recording) — a deliberate scope limit, not an oversight,
  since STK Push inherently targets the payer's own phone.
- No UI to regenerate a chama's `callback_token` if it's ever suspected to have leaked — would
  require re-pasting the new callback URL into the Safaricom developer portal too, so it's left
  for a future settings-page pass rather than half-built now.
- `penalty_income` still has nothing posting to it (carried over from Phase 3) — unaffected by
  this phase.
- Loans, reports, and chama settings remain the three honest "not built yet" placeholder pages.

### Security notes

Every new financial route re-verifies active membership and `manage-finances` via the same
`ChecksChamaPermissions` pattern as every earlier phase (`PaymentGatewayCredentialPolicy`,
`PaymentTransactionPolicy`), with cross-tenant denial covered by dedicated tests. The one
genuinely new surface is `DarajaCallbackController`, which has no authenticated user at all by
necessity — its authenticity model (unguessable per-chama token + matching against a transaction
this app itself initiated) is documented in full in `docs/SECURITY.md`'s "Payments (Phase 4)"
section, including why IP allowlisting was not used as the primary guard (Safaricom's public
Daraja API does not sign callbacks, and its published IP ranges are not something this
architecture should hard-depend on).

### Next step

Verify this phase against a real Daraja sandbox app and a reachable callback URL before trusting
it with a real chama (see Known issues). Beyond that, per the roadmap: Phase 5 (Android, via the
already-scaffolded `/api/v1` routes) or Phase 6's advanced modules (merry-go-round, loans,
meetings, investments) are the next unstarted phases; financial reporting depth (income
statement/balance sheet) remains an open enhancement on top of Phase 3's ledger. Verifying
migrations against real MySQL 8 remains the standing recommendation from every phase so far.

---

## Phase 5 — REST API (Flutter Android deferred)

**Status:** v1 REST API complete and tested. The Flutter app itself — nominally also "Phase 5"
per the roadmap — is deliberately not started this session; see below for why.

### Scope decision, made with the user before writing any code

Phase 5 per the roadmap is "Android (Flutter)." This container has no Flutter/Dart SDK and,
more importantly, no Android emulator or device — so even after installing Flutter, there would
be no way to actually run the app and see it work, breaking the verification standard every
prior phase was held to (real browser screenshots for every web feature, real HTTP round-trips
for Phase 4's payment flows). Asked explicitly, the user chose to build the REST API the mobile
app will consume first, fully tested, and pause there rather than write Flutter code nobody
(human or AI) could run and watch work in this environment. Building a mobile UI against
unverifiable tooling would have produced the one thing this project has consistently avoided:
code whose correctness is merely asserted, not demonstrated.

### Completed

- **Extracted the business logic every protected API write-endpoint needed to reuse, instead of
  duplicating it** (Part 15: the API must never hold its own copy of financial/business rules).
  Four controllers (`ContributionController`, `WithdrawalController`, `ExpenseController`,
  `FinancialAccountController`) had their write-action logic living directly in the controller
  method bodies; each was pulled out into a dedicated service
  (`App\Services\{Contributions\ContributionService,Withdrawals\WithdrawalService,Expenses\ExpenseService,Accounts\FinancialAccountService}`),
  with the full pre-existing test suite re-run after **every individual extraction** to confirm
  zero behavior change before moving to the next one. The web controllers now call these services
  too — there is exactly one implementation of "verify a contribution" or "pay a withdrawal," not
  a web copy and an API copy.
- Sanctum-based token authentication (`App\Http\Controllers\Api\V1\AuthController`): register,
  login (by username or email, case-insensitively, same as web), logout (revokes the specific
  token used), and `/auth/me`. This is the first actual implementation of the "device/session
  management" `docs/SECURITY.md` has described as a Phase 1 feature since it was written — nothing
  issued or revoked a Sanctum token anywhere in the codebase before this phase.
- A full `/api/v1` surface mirroring the web app's chama-scoped features: chamas (list mine with
  my per-chama roles, show), members, contributions (record/verify/reject/reverse,
  M-Pesa STK Push initiate/status), contribution schedules, withdrawals (request through the full
  approve/pay/cancel/reverse lifecycle), expenses, financial accounts (create/show/reconcile),
  accounting (chart of accounts), and the transaction journal. Every protected route sits behind
  `auth:sanctum` + `verified` + the same `tenant` middleware and Policy classes the web app uses —
  tenant isolation is identical, not a parallel implementation (`docs/TENANCY.md`).
- API Resource classes (`app/Http/Resources/*`) for every exposed model, each monetary field
  given both as integer cents (what the app computes with) and a pre-formatted string (so no
  client has to reimplement `Money::format()`).
- M-Pesa STK Push needed no separate API controller at all: `StkPushController` already branched
  on `$request->wantsJson()` since Phase 4, so the same controller class is simply routed under
  `/api/v1` too.
- `docs/API.md` — full endpoint reference, auth model, response conventions, and the
  services-not-duplicated architecture note, written as what a future Flutter (or any other
  client) session should read first.

### Files created

`app/Services/Contributions/ContributionService.php`,
`app/Services/Withdrawals/WithdrawalService.php`, `app/Services/Expenses/ExpenseService.php`,
`app/Services/Accounts/FinancialAccountService.php`,
`app/Http/Controllers/Api/V1/{AuthController,ChamaController,MemberController,ContributionController,ContributionScheduleController,WithdrawalController,ExpenseController,FinancialAccountController,AccountingController,TransactionController}.php`,
`app/Http/Resources/{UserResource,OrganizationResource,ChamaMembershipResource,ContributionScheduleResource,ContributionObligationResource,ContributionResource,WithdrawalRequestResource,ExpenseResource,FinancialAccountResource,LedgerAccountResource,JournalEntryResource,PaymentTransactionResource,AccountReconciliationResource}.php`,
`tests/Feature/Api/V1/{AuthTest,ChamaTest,ContributionApiTest,WithdrawalApiTest,ExpenseAccountApiTest}.php`,
`docs/API.md`.

### Files modified

`app/Http/Controllers/{ContributionController,WithdrawalController,ExpenseController,FinancialAccountController}.php`
(reduced to thin adapters calling the new services), `routes/api.php` (the full `/api/v1`
surface, public auth routes + `auth:sanctum`+`verified`+`tenant`-protected resource routes).

### Tests executed

`php artisan test` → **170 passed, 477 assertions** (up from 145 — 25 new API tests, zero
regressions from the service extractions). `vendor/bin/pint --test` → clean. The full
golden-path round trip was driven through **real HTTP requests** against `php artisan serve`
(the API equivalent of this project's real-browser standard for web UI, since there's no browser
involved here): login as the seeded `ken` user, call `/auth/me`, list chamas and see
`my_roles: ["chairperson"]`, fetch the contributions page's data (obligations, outstanding
balance, pending verifications), and fetch the accounting/chart-of-accounts endpoint and confirm
`is_balanced: true` against the real seeded ledger — plus confirming a request with no token gets
401 and a request for a nonexistent/foreign organization gets 404.

**Two real things were caught during this work, not left as known issues:**

1. **A latent audit-log gap, caught by reasoning rather than by a failing test.** Every
   extracted service originally relied on `AuditLogger::log()`'s `$userId ?? auth()->id()`
   fallback, exactly as the original controllers had. `auth()->id()` resolves against the
   **default** guard, which `config/auth.php` defines as `web` (session-based) — for a stateless
   API request authenticated via the separate `sanctum` guard, that call returns `null`. Confirmed
   directly (`config('auth.guards')` only defines `web`) before it ever shipped: every audit log
   entry written by an API-originated financial action would have silently recorded
   `user_id: null`, a real accountability gap for exactly the actions (verifying a payment,
   approving a withdrawal) where "who did this" matters most. Fixed by having every service method
   pass `userId: $actor->id` explicitly rather than depend on the ambient guard.
2. **A Laravel/Sanctum testing-harness gotcha, not a production bug** — documented in detail in
   `tests/Feature/Api/V1/AuthTest.php` and worth recording here too so it isn't rediscovered the
   hard way: `Illuminate\Auth\RequestGuard` (which Sanctum's token guard is built on) caches the
   resolved user on itself for the guard instance's lifetime, and Laravel's `AuthManager` caches
   guard instances per name. Two real production HTTP requests always get a fresh container and
   therefore a fresh guard, so this never affects real traffic — but two `$this->postJson()` calls
   inside *one* PHPUnit test method share the same container, so a naive "logout, then immediately
   try to use the same token again" test would pass for the wrong reason (reading the first
   request's cached guard, never re-checking the database). Verified by direct inspection (dumping
   token counts and guard state between calls) before concluding this and fixing it with
   `$this->app['auth']->forgetGuards()` between the two calls, rather than just declaring the
   revocation test green once it happened to pass.

### Known issues

- **The Flutter Android app itself does not exist yet** — this phase is the REST API it will
  consume, built and verified as thoroughly as this environment allows (real HTTP round-trips,
  no mocking of the app's own stack), but there is no Dart code, no Flutter project, and nothing
  to run on a device because this container cannot provide a device or emulator to verify against.
  Starting it needs a session with real Flutter tooling and ideally a device/emulator so the
  finished app can be visually verified the way every other phase's UI has been.
- No per-token ability scoping (Sanctum supports it; nothing yet needs narrower-than-full tokens).
- No token/device list-and-revoke UI on the web profile page — a user can't yet see or revoke
  their own active API tokens from the website, only via direct database access or by the token
  simply expiring/being replaced. Worth adding alongside or before the mobile app ships.
- Registration and chama-creation still require real email verification before most endpoints
  work, same as web — there's no "skip verification for mobile" shortcut, by design.
- No API rate limiting beyond the 5-per-minute login/register throttle; the resource endpoints
  rely on the same infrastructure-level protections as the web app.

### Security notes

Every protected endpoint re-verifies active membership and, where relevant, `manage-finances` via
the same `ChecksChamaPermissions`-backed policies as the web app — there is no separate,
potentially-drifting API authorization model. Tokens are bearer credentials with no built-in
expiry configured (Sanctum's default); logout revokes the specific token used, not every token a
user holds, matching normal "log out this device" mobile UX. The self-approval block on
withdrawals, the auto-verification-only-via-Daraja-callback rule, and every other Phase 3/4
invariant apply identically through the API, confirmed by a cross-tenant-denial test on every new
resource.

### Next step

Build the Flutter Android app against this API in a session with actual Flutter/Dart tooling and,
ideally, a device or emulator — read `docs/API.md` first. Independently of mobile: Phase 6's
advanced modules (merry-go-round, loans, meetings, investments) or financial reporting depth
remain open, and verifying migrations against real MySQL 8 is still the standing recommendation
from every phase so far.

---

## How to resume work in a new session

1. Read this file top to bottom — it states the actual current phase and what remains.
2. Run `cd backend && composer install && php artisan test` to confirm the existing test suite
   still passes before adding anything.
3. Check `git log` on `claude/chama-wise-saas-platform-jxvjv5` for the real commit history —
   this file is a summary, the commits are the ground truth.
4. Continue with the "Next step" noted above, or the next unstarted phase in
   `docs/MASTER_PROJECT_SPECIFICATION.md` / the original brief, Part 35.
