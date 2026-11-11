# CHAMA WISE — REST API (v1)

The versioned REST API introduced in Phase 5 (Part 15/Part 26), built so the Flutter Android app
(not yet built — see "Known issues") and any other API client can do everything the web app does
without a second, drifting implementation of the business rules. Every protected endpoint calls
the exact same Policy classes and the exact same service classes
(`App\Services\{Contributions\ContributionService,Withdrawals\WithdrawalService,Expenses\ExpenseService,Accounts\FinancialAccountService,Ledger\LedgerPoster,Payments\StkPushService}`)
that the web controllers call — a web controller and an API controller are two thin adapters
over one shared core, never two copies of the rules.

## Base URL and versioning

All endpoints are under `/api/v1/...`. There is no `v2` yet; a future breaking change gets a new
`/api/v2` prefix rather than changing `v1`'s behavior under API clients that already depend on it.

## Authentication

Laravel Sanctum personal access tokens (Part 7's "device/session management" — the first actual
implementation of it; `docs/SECURITY.md` had described the intended design since Phase 1 but
nothing issued or revoked a token before this phase).

```
POST /api/v1/auth/register   { name, username, email, password, password_confirmation, device_name }
POST /api/v1/auth/login      { login, password, device_name }   — login = username OR email
```

Both return `201`/`200` with `{ "token": "...", "user": {...} }`. Send the token on every
subsequent request:

```
Authorization: Bearer <token>
Accept: application/json
```

```
GET  /api/v1/auth/me         → { "data": { ...user } }
POST /api/v1/auth/logout     → revokes the token used to make this exact request
```

Login reuses `User::loginFieldFor()`/`User::normalizeUsername()` so "either username or email,
case-insensitively" behaves identically to the web login form — but it is **not** the same code
path as the web `LoginRequest`, which authenticates against the stateful `web` session guard. A
mobile request is stateless, so `AuthController` checks credentials directly and issues a token
instead of starting a session. Login is rate-limited (5 attempts per login+IP, same policy as
web); registration and login endpoints are also behind Laravel's `throttle:5,1` middleware.

Every other protected route additionally requires a **verified email** (`verified` middleware),
exactly matching the web app's `['auth','verified']` group — an unverified user can log in and
get a token, but every chama-scoped endpoint returns `403` until they verify.

## Tenant isolation

Identical model to the web app (`docs/TENANCY.md`): the `tenant` middleware
(`SetPermissionsTeamFromOrganization`) sets the permission "team" context from the route-bound
`{organization}`, and every controller action still re-authorizes through a Policy that
re-verifies the authenticated user's actual active membership for that specific organization —
the route parameter is never trusted on its own. A token issued to a member of chama A gets a
`403`/`404` on every chama B endpoint, covered by a dedicated cross-tenant test on every resource
in `tests/Feature/Api/V1/`.

## Response conventions

- A single resource: `{ "data": { ... } }`.
- A collection: `{ "data": [ ... ] }` (paginated endpoints add Laravel's standard
  `links`/`meta` alongside `data`).
- A validation or business-rule error: `422` with `{ "message": "...", "errors": {...} }` (or just
  `{ "message": "..." }` for a non-field-specific rule, e.g. "you cannot withdraw more than your
  balance").
- Unauthenticated: `401`. Authenticated but not allowed: `403`. Not found / not in this
  organization: `404`.
- Every monetary field is exposed as both `..._cents` (the integer the app actually computes
  with — see `docs/FINANCIAL_LEDGER.md`) and a human `..._formatted` string, so a client never
  has to reimplement `Money::format()`.

## Endpoints

### Chamas

```
GET /api/v1/chamas                        my active chamas, each with my_roles for that chama
GET /api/v1/chamas/{organization}         one chama (must be an active member)
GET /api/v1/chamas/{organization}/members membership list
```

### Contributions

```
GET  /api/v1/chamas/{organization}/contributions
     → my obligations, my payment history, and (if manage-finances) the pending-verification queue
POST /api/v1/chamas/{organization}/contributions
     { user_id?, contribution_schedule_id?, amount, payment_method, transaction_reference?, paid_at, notes? }
POST /api/v1/chamas/{organization}/contributions/{contribution}/verify   { financial_account_id }
POST /api/v1/chamas/{organization}/contributions/{contribution}/reject
POST /api/v1/chamas/{organization}/contributions/{contribution}/reverse

GET  /api/v1/chamas/{organization}/contributions/schedules
GET  /api/v1/chamas/{organization}/contributions/schedules/{schedule}
```

### M-Pesa STK Push (Phase 4)

```
POST /api/v1/chamas/{organization}/contributions/mpesa            { phone, amount (whole shillings) }
GET  /api/v1/chamas/{organization}/contributions/mpesa/{transaction}   → poll until is_terminal
```

These two routes point at the **same** `App\Http\Controllers\StkPushController` the web
"Pay with M-Pesa" panel uses — it already branches on `$request->wantsJson()`, so no separate API
controller exists for this (there would be nothing left for it to do differently). See
`docs/FINANCIAL_LEDGER.md`'s Phase 4 section: initiating a push is never itself proof of payment;
only Safaricom's callback can mark one completed, same as from the web.

### Withdrawals

```
GET  /api/v1/chamas/{organization}/withdrawals
     → my requests, and (if manage-finances) the pending-approvals and ready-to-pay queues
POST /api/v1/chamas/{organization}/withdrawals          { financial_account_id, amount, reason? }
POST /api/v1/chamas/{organization}/withdrawals/{withdrawal}/approve
POST /api/v1/chamas/{organization}/withdrawals/{withdrawal}/reject   { reason? }
POST /api/v1/chamas/{organization}/withdrawals/{withdrawal}/pay
POST /api/v1/chamas/{organization}/withdrawals/{withdrawal}/cancel
POST /api/v1/chamas/{organization}/withdrawals/{withdrawal}/reverse
```

Self-approval is blocked at the policy layer regardless of client — a `manage-finances` holder
still gets `403` approving their own request via the API, exactly as on web.

### Expenses

```
GET  /api/v1/chamas/{organization}/expenses
POST /api/v1/chamas/{organization}/expenses             { financial_account_id, amount, description, expense_date }
POST /api/v1/chamas/{organization}/expenses/{expense}/reverse
```

### Financial accounts, accounting, transactions

```
GET  /api/v1/chamas/{organization}/accounts
GET  /api/v1/chamas/{organization}/accounts/{account}        → includes recent postings + reconciliations
POST /api/v1/chamas/{organization}/accounts                  { name, type, account_number?, opening_balance?, is_default? }
POST /api/v1/chamas/{organization}/accounts/{account}/reconcile   { statement_date, statement_balance, notes? }

GET  /api/v1/chamas/{organization}/accounting     chart of accounts by type + debits==credits sanity check
GET  /api/v1/chamas/{organization}/transactions   paginated journal, each entry with its full posting lines
```

### M-Pesa Daraja callback (not part of the versioned client API)

`POST /api/mpesa/callback/{organization}/{token}` — unauthenticated, called by Safaricom, not by
the Flutter app or any other client. Documented in `docs/SECURITY.md`'s "Payments (Phase 4)"
section; listed here only so it isn't mistaken for a missing `v1` route.

## What changed under the web app to make this possible

Before this phase, `ContributionController`, `WithdrawalController`, `ExpenseController`, and
`FinancialAccountController` held their write-action business logic directly in the controller
method bodies. Adding an API controller that called the same logic would have meant copying that
logic a second time — exactly what Part 15 forbids. Each controller's business logic was
extracted first, with the full existing test suite (145 tests at the time) re-run after every
single extraction to confirm zero behavior change, into:

- `App\Services\Contributions\ContributionService` (record/verify/reject/reverse)
- `App\Services\Withdrawals\WithdrawalService` (request/approve/reject/pay/cancel/reverse)
- `App\Services\Expenses\ExpenseService` (record/reverse)
- `App\Services\Accounts\FinancialAccountService` (create/reconcile)

The web controllers now call these services too — they were not left as a parallel path. Policy
authorization (`$this->authorize(...)`) deliberately stayed in the controllers rather than moving
into the services, since "who may do this" is a per-interface HTTP concern (route model binding,
`abort()` semantics) layered on top of the same underlying state-machine rules the services
enforce regardless of caller.

## A real bug this phase's own audit trail caught

Every service method takes the acting `User` explicitly and passes `userId: $actor->id` to
`AuditLogger::log()` rather than relying on that method's `$userId ?? auth()->id()` fallback.
This was deliberate, not stylistic: `auth()->id()` resolves against the **default** guard
(`web`, session-based — see `config/auth.php`), which is `null` for a stateless API request
authenticated via the separate `sanctum` guard. Before this was caught, every audit log entry
written by an API-originated action would have silently recorded `user_id: null` — a real
accountability gap for exactly the write actions (verifying a contribution, approving a
withdrawal) that matter most to get right. Found by reasoning through guard resolution while
writing the services, not by a failing test, and fixed before any test was written against it.

## Known issues

- **No Flutter app yet.** Phase 5 per the roadmap is "Android (Flutter)"; this slice is the REST
  API the user asked to land first, with the mobile client itself deferred to a session with
  actual Flutter/Dart tooling and (ideally) a device or emulator to verify against — this
  container has neither (see `docs/DEVELOPMENT_PROGRESS.md`'s Phase 5 entry for the full
  reasoning).
- No API versioned deprecation policy exists yet because there is only one version; write one
  before a `v2` is ever introduced.
- No per-token scopes/abilities — every token can do everything the underlying user's roles
  allow. Sanctum supports scoped abilities (`createToken($name, ['contributions:read'])`); not
  used yet because there's no client (Flutter or otherwise) that would need a narrower token.
- Pagination metadata (`links`/`meta`) is Laravel's default shape on the two paginated endpoints
  (`accounts/{account}` postings, `transactions`) — not yet documented field-by-field since no
  client consumes it yet.
