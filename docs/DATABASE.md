# CHAMA WISE — Database Design (Phase 1 slice)

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

## Entity relationships

```
users 1───* chama_memberships *───1 organizations
users 1───* (spatie model_has_roles, team-scoped by organization_id)
organizations 1───* invitations
users, organizations 1───* audit_logs (both nullable depending on action scope)

organizations 1───* contribution_schedules 1───* contribution_obligations *───1 users
contribution_schedules 1───* contributions *───1 users
contributions 1───* contribution_allocations *───1 contribution_obligations
```

Full ERD is expanded in `docs/ERD.md` once there's enough schema across phases to make a diagram
worthwhile rather than restating this table-by-table list.
