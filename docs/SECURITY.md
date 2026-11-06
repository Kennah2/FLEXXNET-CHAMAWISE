# CHAMA WISE — Security

## Authentication

- Passwords hashed with Laravel's default (bcrypt) hasher; never stored or logged in plaintext.
- Every account has a unique `username` (chosen at registration, editable from the profile page)
  separate from its email. Members log in with either — `LoginRequest` detects which one was
  entered (`User::loginFieldFor()`) and matches it against the right column. Usernames are always
  compared case-insensitively, normalized in PHP (`User::normalizeUsername()`) rather than left to
  the database's collation — MySQL's default collation is case-insensitive, SQLite's is not, so
  relying on the database would make login behavior differ between the dev/test environment and
  production.
- Email verification required before a user can create or join a chama (Laravel's built-in
  `MustVerifyEmail`). Email remains the channel for verification and invitation matching — it is
  never used as the login identifier's only option, but changing it still re-triggers
  verification.
- Rate limiting on login, registration, and password-reset routes using Laravel's throttle
  middleware.
- Web sessions use Laravel's signed, encrypted session cookies; CSRF protection is on by default
  for all state-changing web routes.
- API/mobile authentication uses Laravel Sanctum personal access tokens, scoped per device, with
  the ability to list and revoke individual tokens from the user's profile ("device/session
  management" — Part 7). Tokens are never logged.

## Authorization & tenant isolation

See `docs/TENANCY.md`. Every chama-scoped action is checked by a policy that re-verifies the
authenticated user's active membership and role/permission for that specific organization — the
route's organization id is never trusted on its own.

## Audit logging

An append-only `audit_logs` table records sensitive actions from Phase 1 onward: who
(`user_id`), in which chama (`organization_id`, nullable for platform-level actions), what
action, on what resource (`auditable_type`/`auditable_id`), before/after state where relevant,
and timestamp. IP address is recorded only where it serves a real security purpose (auth events,
role changes), per Part 30. Audit log rows are never updatable or deletable through the
application — only inserted. Never logged: passwords, API tokens/secrets, M-Pesa PINs, Daraja
credentials.

## File uploads (introduced in later phases)

Validated by MIME type, extension, and size; stored outside the public web root; served through
an authenticated, tenant-checked download controller rather than a predictable public URL.

## Secrets

Platform-level third-party credentials (mail, SMS, etc.) live in `.env`, excluded from version
control via `.gitignore`, and are never written to client-side code, logs, or API responses.
**Daraja credentials are the one exception** — they are per-chama, not platform-level, and are
encrypted at rest in the database rather than in `.env`. See "Payments (Phase 4)" below.

## Payments (Phase 4)

Each chama holds its **own** Safaricom Daraja app credentials (`payment_gateway_credentials`,
one row per organization) rather than the platform holding one shared Daraja app for every
chama — see the custody limitation below for why. `consumer_key`, `consumer_secret`, and
`passkey` are encrypted at rest via Eloquent's `encrypted` cast (backed by `APP_KEY`); the model
also marks them `$hidden` so an accidental `toArray()`/`toJson()` never leaks them. They are
never re-displayed to the browser after being saved — the settings form shows a masked
placeholder and a blank field is understood as "keep the current value," never "clear it" (see
`PaymentGatewayCredentialController::update()`).

**Callback authenticity.** Safaricom's public Daraja API does not sign its STK Push callback
payloads with a shared secret the receiver can verify — unlike many modern webhook providers,
there is no HMAC signature header to check. The callback URL therefore embeds an unguessable,
per-chama `callback_token` (`Str::random(48)`, generated once when a chama's credentials are
first saved) as a path segment:
`/api/mpesa/callback/{organization}/{token}`. `DarajaCallbackController` compares it with
`hash_equals()` before doing anything else, and the handler additionally only acts on a
`CheckoutRequestID` that matches a `PaymentTransaction` this application itself created and is
still awaiting a result for — so even a leaked token could only replay a result for a payment
attempt that genuinely happened, not fabricate an arbitrary one. The endpoint is intentionally
unauthenticated (Safaricom sends no bearer token) and always acknowledges with HTTP 200 even when
it rejects or ignores a payload, to avoid Safaricom's retry behavior for a request it correctly
recognized as invalid.

**STK Push initiation is never proof of payment.** `PaymentTransaction.status` only reaches
`completed` — and only then does `DarajaCallbackHandler` create a `Contribution` and post the
ledger — in reaction to a callback reporting `ResultCode: 0`. Initiating the push, or Safaricom
accepting the request for processing, leaves the transaction at `pending`; nothing is assumed
paid until Safaricom's own callback says so (Part 36).

## Data protection

Built with the Kenya Data Protection Act, 2019 in mind: data minimization in what's collected,
role-gated access to personal and financial data, and a retention posture that keeps financial
records even if a user account is later closed (historical correctness over convenience — see
`docs/TENANCY.md` on why membership rows are never deleted). This document will be expanded with
a privacy notice and formal retention policy before commercial launch (Phase 8); no compliance
or certification claim is made until a proper legal review has happened.

## Custody limitation

CHAMA WISE Phase 1–4 is a **recordkeeping and management** platform. It records and verifies
payments; it does not take custody of pooled group funds. A pooled-wallet/custody feature would
require separate legal, regulatory and financial-architecture review before being built — it is
explicitly out of scope unless and until that review happens.

This is the specific reason Phase 4's Daraja integration gives each chama its own Safaricom app
credentials rather than the platform operating one shared paybill/till for every chama on the
platform: a shared platform-wide till receiving every chama's contributions would be CHAMA WISE
itself pooling member funds, which is exactly what this limitation rules out without the review
above. With per-chama credentials, Safaricom pays each chama's own shortcode directly — CHAMA
WISE only asks Safaricom to prompt the member and records whatever Safaricom reports back.
