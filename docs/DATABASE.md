# CHAMA WISE — Database Design (through Phase 4)

Full target schema is the domain inventory in Part 29 of the product brief, built out
incrementally per phase. This document covers what Phase 1 actually creates; later phases append
their own sections here as they land.

## Conventions

- All monetary columns (introduced from Phase 2 onward) use `DECIMAL(14,2)` at minimum — never
  `FLOAT`/`DOUBLE`.
- Every chama-owned table has a non-nullable, indexed `organization_id` foreign key.
- Soft deletes are used only where "undo" is a real business operation (e.g. draft invitations);
  posted financial records are never soft-deleted — they're reversed via linked entries instead
  (Phase 3).
- Timestamps (`created_at`, `updated_at`) on every table; audit-relevant tables also get actor
  columns (`created_by`, etc.) where meaningful.

## Phase 1 tables

### `users`
Laravel's default columns plus: `is_platform_admin` (bool, default false — reserved for Phase 7
platform administration, not exposed anywhere yet), `phone`, `phone_verified_at`.

### `organizations`
The chama itself (named `organizations` in the schema to match spatie/laravel-permission's
"team" vocabulary and to leave room for non-chama tenant types later without a rename).
Columns: `id`, `name`, `slug`, `description`, `group_type` (enum: `savings`, `merry_go_round`,
`table_banking`, `investment`, `welfare`, `other`), `currency` (default `KES`), `formation_date`,
`contact_email`, `contact_phone`, `location`, `status` (`active`, `suspended`), `created_by`
(user id), `settings` (JSON — for the configurable rules described in Part 8: contribution
frequency/amount/deadline/grace period/penalty defaults, consumed by Phase 2's contribution
schedules rather than hardcoded), timestamps.

### `chama_memberships`
`id`, `organization_id` (FK → organizations, restrict on delete), `user_id` (FK → users,
restrict on delete), `membership_number` (string, unique per organization), `status` (enum:
`pending`, `active`, `suspended`, `resigned`, `removed`), `joined_at`, `left_at` (nullable),
timestamps. Unique index on `(organization_id, user_id)`. Roles are **not** a column here — they
live in spatie/laravel-permission's team-scoped tables (`model_has_roles` etc. with
`organization_id` as the team key), joined through this membership conceptually, not by schema.

### `invitations`
`id`, `organization_id`, `invited_by` (user id), `email`, `role` (string, the role to grant on
acceptance), `token` (unique, hashed), `status` (`pending`, `accepted`, `revoked`, `expired`),
`expires_at`, timestamps.

### `audit_logs`
`id`, `user_id` (nullable — system actions), `organization_id` (nullable — platform-level
actions), `action` (string, e.g. `membership.invited`), `auditable_type`, `auditable_id`,
`before` (JSON, nullable), `after` (JSON, nullable), `ip_address` (nullable), `created_at` only
(append-only, no `updated_at`).

### spatie/laravel-permission tables
`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` —
standard package migration, configured with `teams` enabled and `organization_id` as the team
foreign key. Seeded default roles per Part 10: `chairperson`, `treasurer`, `secretary`,
`member`, `auditor` — seeded as data, not hardcoded into application logic, so a chama (or the
platform admin) can introduce additional custom roles later without a code change.

## Phase 2 tables (contributions)

All monetary columns here are **integer minor units** (cents), not `DECIMAL` — see
`docs/FINANCIAL_LEDGER.md` for why (no `bcmath` extension available in this environment, and
integer arithmetic needs none).

### `contribution_schedules`
One per chama's contribution arrangement (Part 11): `organization_id`, `name`, `description`,
`frequency` (`daily`/`weekly`/`monthly`/`custom`), `currency`, `amount_cents`, `start_date`,
`end_date` (nullable), `deadline_time`, `timezone` (default `Africa/Nairobi`),
`grace_period_days`, `penalty_amount_cents`, `applies_to` (`all`/`selected`), `status`
(`active`/`closed`), `created_by`. `start_date`/`end_date` are cast `date:Y-m-d` specifically so
the stored format matches what lookup queries search for (see Known Issues in
`docs/DEVELOPMENT_PROGRESS.md` Phase 2 — this was a real bug, not a stylistic choice).

### `schedule_members`
Pivot used only when `applies_to = 'selected'`: `contribution_schedule_id`, `user_id`.

### `contribution_obligations`
One expected contribution for one member for one period: `contribution_schedule_id`,
`organization_id` (denormalized for tenant-scoped queries), `user_id`, `period_start`/
`period_end` (cast `date:Y-m-d`, same reason as above), `due_at`/`grace_expires_at` (`datetime`,
always persisted as UTC — see `docs/FINANCIAL_LEDGER.md`'s timezone pitfall section),
`amount_due_cents`, `amount_paid_cents` (mutated only by `ContributionAllocator`), `status`
(`pending`/`partial`/`paid`/`overdue`/`waived`), `penalty_amount_cents`, `penalty_status`
(`none`/`assessed`/`waived`/`collected`), `penalty_waived_by`/`penalty_waived_reason`. Unique on
`(contribution_schedule_id, user_id, period_start)`.

### `contributions`
A recorded payment (Part 13), not proof of a successful one until verified:
`organization_id`, `user_id`, `contribution_schedule_id` (nullable — a general/unscheduled
payment), `amount_cents`, `currency`, `payment_method`, `transaction_reference` (nullable, **not**
a unique column — see `docs/FINANCIAL_LEDGER.md`), `paid_at`, `recorded_by`, `status`
(`pending_verification`/`verified`/`rejected`/`reversed`), `verified_by`/`verified_at`, `notes`.

### `contribution_allocations`
The auditable link between one verified contribution and the obligation(s) it pays down:
`contribution_id`, `contribution_obligation_id`, `amount_cents`, `reversed_at` (nullable — a
reversal stamps this rather than deleting the row).

## Phase 3 tables (financial ledger)

All monetary columns here are **integer minor units** (cents), consistent with Phase 2 — see
`docs/FINANCIAL_LEDGER.md` for the full ledger design and why `LedgerPoster` is the only code path
allowed to write `journal_entries`/`ledger_postings` rows.

### `financial_accounts`
A real-world place money sits (Part 17): `organization_id`, `name`, `type`
(`cash`/`bank`/`mobile_money`/`other`), `account_number` (nullable), `currency`,
`opening_balance_cents`, `is_default` (exactly one per org, enforced in
`FinancialAccountController::store()` rather than at the schema level since "unset the others" is
multi-row), `is_active`, `created_by`.

### `ledger_accounts`
The chart-of-accounts entry backing a `financial_account`, plus the fixed system accounts: `code`
(unique per org, e.g. `member_savings`), `name`, `type` (`asset`/`liability`/`income`/`expense`),
`is_system` (true for the three auto-provisioned accounts — see below — false for a
user-created financial account's backing ledger account), `financial_account_id` (nullable FK —
null for a system account that isn't tied to one real-world account). `balanceCents()` applies
normal-side accounting (debit increases asset/expense, credit increases liability/income) rather
than a naive debit-minus-credit for every type.

### `journal_entries`
One balanced transaction (Part 17's double-entry requirement): `organization_id`, `description`,
`source_type`/`source_id` (polymorphic — links back to the `Contribution`, `Expense`, or
`WithdrawalRequest` that caused the posting, nullable for a manual entry), `posted_at`,
`created_by`, `status` (`posted`/`reversed`), `reverses_journal_entry_id` (nullable, self-referencing
FK — set on the mirror entry a reversal creates, never on the original).

### `ledger_postings`
One debit or credit line of a journal entry: `journal_entry_id`, `ledger_account_id`,
`organization_id` (denormalized for tenant-scoped queries), `user_id` (nullable — lets one control
account like `member_savings` carry a per-member sub-ledger without a separate ledger account per
member), `debit_cents`, `credit_cents` (a line is always exactly one of these, enforced by
`LedgerPoster::assertBalanced()`, never both or neither).

### `withdrawal_requests`
A member's request to withdraw their savings (Part 18): `organization_id`, `user_id` (whose
savings), `requested_by` (usually the same as `user_id`, but an official can request on a member's
behalf), `financial_account_id` (payout source), `amount_cents`, `reason` (nullable), `status`
(`pending`/`approved`/`rejected`/`paid`/`reversed`/`cancelled`), `required_approvals` (snapshotted
from the org's setting at request time, so a later setting change doesn't retroactively alter an
in-flight request), `paid_by`/`paid_at` (nullable), `journal_entry_id` (nullable — set only once
paid).

### `withdrawal_approvals`
One official's decision on a withdrawal request: `withdrawal_request_id`, `user_id`, `decision`
(`approved`/`rejected`), `reason` (nullable). Unique on `(withdrawal_request_id, user_id)` — one
decision per official per request. Self-approval is blocked in `WithdrawalRequestPolicy`, not by
this table's schema, since the schema alone can't distinguish "the requester" from "an official."

### `expenses`
A chama expense paid out of a financial account (Part 17): `organization_id`,
`financial_account_id`, `ledger_account_id` (which expense category — currently always
`general_expense`, the system account; `type` exists for future categorization), `amount_cents`,
`description`, `expense_date` (cast `date:Y-m-d`, same reasoning as Phase 2's date columns),
`recorded_by`, `status` (`posted`/`reversed`), `journal_entry_id`.

### `account_reconciliations`
A point-in-time comparison between a bank/till statement and the ledger (Part 17): `organization_id`,
`financial_account_id`, `statement_date`, `statement_balance_cents`, `computed_balance_cents` (the
ledger balance at the time of reconciliation), `variance_cents` (`statement - computed`; zero means
balanced), `notes` (nullable), `recorded_by`. Reconciliation is a record, never a correction — a
non-zero variance is surfaced, not auto-fixed; closing it requires a real adjustment (expense,
withdrawal, or manual journal entry) that explains the difference.

### Links added to `contributions`
Phase 2's `contributions` table gains `financial_account_id` and `journal_entry_id` (both nullable
FK, added in `2026_11_06_055949_add_ledger_links_to_contributions_table`) — set once a contribution
is verified and posted to the ledger, so a contribution's ledger entry (and its reversal) can be
found directly from the payment record.

### System ledger accounts
Three accounts are auto-provisioned per organization by `LedgerProvisioner::provisionSystemAccounts()`
(idempotent — called defensively from every entry point that might run before an org's accounts
exist): `member_savings` (liability — what the chama owes its members in aggregate, split by
`ledger_postings.user_id` into each member's sub-balance), `penalty_income` (income — not yet
posted to by any controller; reserved for when penalty collection is wired into the ledger),
`general_expense` (expense — where `ExpenseController` posts every recorded expense).

## Phase 4 tables (M-Pesa STK Push payments)

See `docs/FINANCIAL_LEDGER.md`'s "M-Pesa STK Push collection (Phase 4)" section and
`docs/SECURITY.md`'s "Payments (Phase 4)" section for the design rationale (per-chama credentials,
callback authenticity, why initiation is never proof of payment).

### `payment_gateway_credentials`
One chama's own Daraja app configuration: `organization_id` (unique per `provider` — one row per
chama per provider), `provider` (default/only value so far: `daraja`), `environment`
(`sandbox`/`production`), `consumer_key`/`consumer_secret`/`passkey` (all `text`, encrypted at
the application layer via an `encrypted` Eloquent cast — never stored or logged in plaintext),
`shortcode` (not secret — this is the paybill/till number members see on their M-Pesa prompt),
`financial_account_id` (which of the chama's accounts a confirmed payment posts into),
`callback_token` (random, unique, generated once — the unguessable path segment that stands in
for the payload signature Safaricom's public Daraja API doesn't provide), `is_enabled`,
`created_by`/`updated_by`.

### `payment_transactions`
One STK Push attempt, from initiation to Safaricom's final word: `organization_id`, `user_id`
(whose contribution this is), `initiated_by` (who clicked pay — normally the same person),
`contribution_schedule_id` (nullable), `contribution_id` (nullable — set only once a successful
callback creates one), `financial_account_id` (snapshotted from the credential at initiation
time), `phone_number` (normalized to `2547.../2541...`), `amount_cents`, `provider`,
`merchant_request_id`/`checkout_request_id` (Safaricom's own identifiers — the latter is how a
callback is matched back to this row), `status`
(`initiated`/`pending`/`completed`/`failed`/`cancelled`), `result_code`/`result_desc`
(Safaricom's own result, copied verbatim), `mpesa_receipt_number`, `raw_callback` (the full
callback JSON, kept for audit/support purposes). Unique on `(organization_id,
checkout_request_id)` — scoped per organization because this app only ever looks one up within
the organization that initiated it, never globally.

## Entity relationships

```
users 1───* chama_memberships *───1 organizations
users 1───* (spatie model_has_roles, team-scoped by organization_id)
organizations 1───* invitations
users, organizations 1───* audit_logs (both nullable depending on action scope)

organizations 1───* contribution_schedules 1───* contribution_obligations *───1 users
contribution_schedules 1───* contributions *───1 users
contributions 1───* contribution_allocations *───1 contribution_obligations

organizations 1───* financial_accounts 1───1 ledger_accounts
organizations 1───* journal_entries 1───* ledger_postings *───1 ledger_accounts
ledger_postings *───1 users (nullable, member sub-ledger)
journal_entries 0───1 journal_entries (self-referencing reversal link)
contributions, expenses, withdrawal_requests }───1 journal_entries (polymorphic source / direct FK)
organizations 1───* withdrawal_requests 1───* withdrawal_approvals *───1 users
organizations 1───* expenses
organizations 1───* account_reconciliations *───1 financial_accounts

organizations 1───1 payment_gateway_credentials 1───1 financial_accounts
organizations 1───* payment_transactions *───1 users
payment_transactions 0───1 contributions (set once a successful callback creates one)
```

Full ERD is expanded in `docs/ERD.md` once there's enough schema across phases to make a diagram
worthwhile rather than restating this table-by-table list.
