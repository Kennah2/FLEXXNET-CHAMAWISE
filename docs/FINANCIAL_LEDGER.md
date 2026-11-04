# CHAMA WISE — Financial Ledger & Money Handling

This document covers how money is represented and calculated, starting with Phase 2
(contributions). The full double-entry ledger (accounts, journal entries, reconciliation) is
Phase 3 — this page will grow a corresponding section when that lands.

## Why integer minor units, not DECIMAL or bcmath

Part 12/28 of the specification is explicit: **never use floating-point arithmetic for money**,
and use either `DECIMAL` columns or integer minor units.

This codebase uses **integer minor units** (cents for KES) — plain PHP integers, never floats —
for every monetary column (`amount_cents`, `amount_due_cents`, `penalty_amount_cents`, etc.),
rather than `DECIMAL` columns paired with bcmath-based arithmetic. The reason is a concrete
compatibility finding, not a style preference: **this development container does not have the
`bcmath` PHP extension installed**, and the target production environment is shared cPanel
hosting (Part 27), whose exact PHP build cannot be assumed to include it either. Integer
arithmetic is exact with zero extension dependency — `10050 + 5000` is unambiguous in a way that
decimal-string math without bcmath is not. If a later audit confirms bcmath is reliably available
in production, DECIMAL columns remain an option, but integer minor units work everywhere
regardless, so they're the safer default per Part 27's general guidance to report and work around
hosting constraints rather than build around an unverified assumption.

`App\Support\Money` is the single place user-entered decimal strings ("100.50") are parsed into
cents and cents are formatted back to a displayable string. It never passes a value through
`(float)` or `floatval()`. Every controller and service that touches money uses this helper for
both directions — no ad hoc `* 100` or `/ 100` scattered through the codebase.

## Contributions domain (Phase 2)

- `contribution_schedules.amount_cents` / `penalty_amount_cents` — the per-member amount and
  missed-contribution penalty for a schedule.
- `contribution_obligations.amount_due_cents` / `amount_paid_cents` / `penalty_amount_cents` —
  what one member owes for one period, how much of it has been paid, and any assessed penalty.
  `amount_paid_cents` is **only ever** mutated by `ContributionAllocator` — never by hand-editing
  an obligation — so it can't drift out of sync with what was actually allocated.
- `contributions.amount_cents` — a recorded payment. Existence of this row is not proof of a
  successful payment (Part 13) — only a `verified` contribution is ever allocated.
- `contribution_allocations.amount_cents` — the auditable link between one verified payment and
  one obligation it paid down (fully or partially). A contribution can fund several obligations
  (oldest period first — FIFO); an obligation can be funded by several contributions.

## Correction model: reversal, never deletion

Per Part 12 ("never silently overwrite or delete a posted financial transaction... correct
errors through linked reversal or adjustment entries"):

- Rejecting a **pending** contribution is fine — nothing was ever allocated, so there's nothing to
  undo.
- Reversing a **verified** contribution (`ContributionController::reverse`,
  `ContributionAllocator::reverse`) does not delete the contribution or its allocation rows. It
  stamps each `contribution_allocations.reversed_at`, decrements the affected obligations'
  `amount_paid_cents` back down, and recomputes their status. The full history — what was paid,
  by whom, when, and that it was later reversed and by whom — stays queryable forever.

## Duplicate payment references

A transaction reference (e.g. an M-Pesa code) is **not** a unique database constraint on the
`contributions` table — a duplicate pending entry can legitimately exist (e.g. the first attempt
was rejected for an unrelated reason, or a treasurer corrects a typo by re-entering). The rule
that actually matters — the same reference must never be verified twice — is enforced in
`ContributionController::verify()` at the moment of verification, checked against already
**verified** contributions only. An earlier version of this migration had a blanket unique
constraint; it was removed after a test caught it silently blocking legitimate re-entry (see
`docs/DEVELOPMENT_PROGRESS.md`).

## A timezone pitfall worth remembering

`due_at` and `grace_expires_at` on an obligation are timestamps computed in the schedule's own
timezone (default `Africa/Nairobi`). Eloquent's `datetime` cast serializes a Carbon value by
printing whatever wall-clock digits it currently holds — it does **not** convert to the app's
configured timezone (UTC) first. Saving a Nairobi-timezone Carbon instance as-is therefore stores
local digits with no timezone marker, and the next read reinterprets those same digits as UTC —
silently shifting every deadline by the zone offset on each round-trip through the database. Any
code that builds a timestamp in a non-UTC timezone and persists it must call `->utc()` first (see
`ObligationGenerator`). This was caught by a dedicated regression test
(`ObligationGenerationTest::test_due_at_survives_a_database_round_trip_in_the_schedules_timezone`)
after a browser-based manual check showed deadlines displaying three hours later than configured.
