# CHAMA WISE — Security

## Authentication

- Passwords hashed with Laravel's default (bcrypt) hasher; never stored or logged in plaintext.
- Email verification required before a user can create or join a chama (Laravel's built-in
  `MustVerifyEmail`).
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

All third-party credentials (mail, SMS, Daraja, etc.) live in `.env`, excluded from version
control via `.gitignore`, and are never written to client-side code, logs, or API responses.

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
