# CHAMA WISE — Financial Ledger & Money Handling

This document covers how money is represented and calculated, starting with Phase 2
(contributions), and the full double-entry ledger (accounts, journal entries, withdrawals,
expenses, reconciliation) added in Phase 3.

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

## Double-entry ledger (Phase 3)

Part 17 requires a proper double-entry ledger, not a single running balance. `LedgerPoster` is the
**only** code path in the application allowed to create `JournalEntry`/`LedgerPosting` rows —
every financial action (verifying a contribution, recording an expense, paying a withdrawal) goes
through it rather than writing postings directly, so the balance invariant below can't be bypassed
by a future feature that forgets to check it.

### The balance invariant

`LedgerPoster::post()` takes a description, a list of debit/credit lines, and an actor, and before
touching the database it runs `assertBalanced()`:

- at least two lines;
- every line is a debit **or** a credit, never both and never neither, and never negative;
- every `ledger_account_id` actually belongs to the organization posting the entry (defense against
  a cross-tenant account id ever reaching this far);
- total debits across all lines equal total credits.

Only if all of that holds does it open a `DB::transaction()` and write the `JournalEntry` and its
`LedgerPosting` rows together. A caller that tries to post an unbalanced entry gets an
`InvalidArgumentException` and nothing is persisted — covered by a test that asserts the database
is untouched after a rejected post, not just that an exception was thrown.

### Chart of accounts and normal-side balances

`LedgerAccount::balanceCents()` does not compute `debits - credits` uniformly — that's only correct
for asset and expense accounts. Liability and income accounts have credit as their normal
(increasing) side, so their balance is `credits - debits`. Getting this backwards is a classic
ledger bug that silently produces negative balances for perfectly healthy liability accounts, so
the type-based `match` is covered directly by `LedgerPosterTest`.

Three system accounts are auto-provisioned per organization (`LedgerProvisioner::provisionSystemAccounts()`,
idempotent, called defensively from several entry points rather than assumed to have run once at
org creation): `member_savings` (liability), `penalty_income` (income, not yet posted to),
`general_expense` (expense). Every `FinancialAccount` a chama creates (a till, a bank account) also
gets its own backing asset `LedgerAccount` via `provisionForFinancialAccount()`.

### Member sub-ledger

A chama doesn't need one `member_savings` ledger account per member — `ledger_postings.user_id`
(nullable) lets a single liability account carry a per-member split. `LedgerAccount::balanceCents($userId)`
filters postings to one member, so "how much does the chama owe Ken" and "what's the chama's total
savings liability" are the same account queried two ways, not two separate data structures that
could drift apart.

### Reversal, never deletion (extended to the ledger)

`LedgerPoster::reverse()` follows the same rule Phase 2 established for contributions: a posted
entry is never edited or deleted. Reversing an entry posts a **new** mirror-image entry (every
line's debit and credit swapped, same `user_id` where set), marks the original `status = reversed`,
and refuses to reverse an already-reversed entry. The original postings are untouched — the full
history of what was posted, when, and that it was later reversed, stays queryable forever.

### Withdrawals: a three-step workflow, not one action

Part 18 separates requesting, approving, and paying out a withdrawal, and this codebase keeps them
as three distinct actions rather than collapsing approval into payment:

- **Approve** requires `required_approvals` (an org setting, default 2, snapshotted onto the
  request at creation time) distinct officials with `manage-finances`, each recorded as a
  `WithdrawalApproval`. **Self-approval is a hard-coded block in `WithdrawalRequestPolicy::approve()`**
  — `$user->id === $withdrawal->user_id` is checked before permission, so it can't be configured
  away by a chama's settings. A single rejection vetoes the whole request regardless of how many
  approvals it already has.
- **Pay** is a separate action, deliberately not folded into the final approval, because a
  member's balance can move between approval and payment (another withdrawal could pay out first,
  or a reversal could land) — `WithdrawalController::pay()` re-validates the current balance against
  the requested amount immediately before posting, and rejects (422) if it no longer holds. This
  is covered by a dedicated test that approves a request, drains the balance with a second
  withdrawal, then asserts the first one's payment attempt is rejected.
- **Reverse** (after payment) posts a mirror journal entry the same way any other reversal does,
  restoring the member's savings balance and the paying account's balance.

### Expenses

A simpler one-step flow (Part 17 doesn't require an approval workflow for expenses the way Part 18
does for withdrawals): recording an expense immediately posts the ledger entry (debit
`general_expense`, credit the paying `FinancialAccount`'s ledger account) and requires
`manage-finances`. Reversal follows the same mirror-entry rule as everything else.

### Reconciliation

An `AccountReconciliation` row is a snapshot comparison, not a correction mechanism — recording one
computes `variance_cents = statement_balance_cents - computed_balance_cents` and stores it
alongside both figures; it never adjusts the ledger itself. A non-zero variance is a signal for a
human to investigate and post a real correcting entry (an expense, a manual adjustment, a
previously-missed contribution), not something the reconciliation feature resolves on its own.

## M-Pesa STK Push collection (Phase 4)

Part 35's Phase 4 is "payments/Daraja" — letting a member pay a contribution by M-Pesa STK Push
(the "Lipa na M-Pesa" prompt) instead of only recording a payment manually. Full design rationale
and the callback-authenticity mechanism are in `docs/SECURITY.md`'s "Payments (Phase 4)" section;
this section covers how it fits into the ledger specifically.

### Per-chama credentials, never a shared platform till

Each chama configures its own Safaricom Daraja app (`PaymentGatewayCredential`, one row per
organization) rather than the platform operating a single paybill for every chama. This is not a
convenience choice — it's required by the custody limitation (Part 28, `docs/SECURITY.md`): a
shared platform-wide till receiving every chama's money would make CHAMA WISE itself the custodian
of pooled member funds, which needs a legal/regulatory review this project explicitly has not had.
With per-chama credentials, Safaricom pays each chama's own shortcode; CHAMA WISE only initiates
the prompt and records Safaricom's own report of what happened.

### The request is never the payment

`StkPushService::initiate()` creates a `PaymentTransaction` row and calls Safaricom, but nothing
about a successful STK Push *request* — Safaricom accepting it for processing, or even the
member entering their PIN — ever marks anything as paid. The transaction sits at `pending` until
Safaricom's asynchronous callback arrives. Only `DarajaCallbackHandler`, reacting to a callback
reporting `ResultCode: 0`, creates a `Contribution` and posts it to the ledger — the same
`LedgerPoster`/`ContributionAllocator` path `ContributionController::verify()` uses for a manually
recorded payment (debit the configured `FinancialAccount`'s ledger account, credit
`member_savings` under the payer's `user_id`).

### Auto-verification, deliberately different from manual recording

A manually recorded contribution needs a human `manage-finances` official to verify it (Part 13)
— the member's own claim that they paid isn't, by itself, trustworthy enough to move obligation
balances. An STK Push contribution skips that step: it is created *already* `STATUS_VERIFIED`,
with `verified_by` left `null` (so an audit trail can tell "nobody manually verified this" apart
from "Ken verified it") and `verified_at` stamped to when the callback arrived. The reasoning is
that Safaricom's own callback **is** the verification — it's a direct report from the payment
network that money actually moved, which is strictly more trustworthy than a member typing in a
transaction code by hand. `payment_method = 'mpesa_stk'` is reserved exclusively for this path and
is deliberately absent from the manual-recording form's options (`Contribution::METHODS`), so it
can never be chosen to shortcut verification by hand.

### Why the callback amount isn't used for arithmetic

Safaricom's callback reports the confirmed amount as a JSON number of whole shillings (STK Push
never deals in fractional amounts — enforced on our side too, see `InitiateStkPushRequest`).
`DarajaCallbackHandler` casts it straight to an int and multiplies by 100 to get cents — a single
safe truncation of a value already guaranteed whole, not the floating-point arithmetic on money
Part 36 forbids (that rule is about summing/dividing fractional float amounts and accumulating
imprecision, not about casting one already-integral JSON number once). The result is compared,
as an integer, against the amount recorded at initiation; a mismatch fails the transaction with a
clear reason instead of posting a ledger entry for whichever figure happened to be wrong.

### Idempotency against Safaricom's callback retries

Safaricom retries a callback that doesn't get acknowledged. `DarajaCallbackHandler` guards against
processing the same result twice by locking the `PaymentTransaction` row and checking it is still
in `initiated`/`pending` before doing anything — a transaction already `completed`/`failed`/
`cancelled` is left untouched on a repeat delivery, so a retried callback can never post the ledger
twice for one payment. The HTTP controller (`DarajaCallbackController`) still acknowledges every
recognized-but-already-processed callback with 200, since *not* acknowledging would just provoke
more retries for no benefit.

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
