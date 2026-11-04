# CHAMA WISE — Multi-Tenancy Design

## Model

CHAMA WISE is multi-tenant at the **chama** level, not at the user-account level. One `User` row
can belong to many `Organization` rows (chamas), with a different role in each, through the
`chama_memberships` pivot:

- `organizations` — one row per chama. The tenant boundary.
- `chama_memberships` — `(user_id, organization_id)`, plus `status`
  (`pending|active|suspended|resigned|removed`), `membership_number`, `joined_at`, etc.
  Historical rows are never deleted when a member leaves — `status` changes instead, so
  financial history keeps a valid foreign key (Part 9, Part 12).
- Roles are assigned per membership via spatie/laravel-permission's **team** feature, with
  `organization_id` as the team id (`setPermissionsTeamId($organizationId)` before any
  role/permission check). This gives "treasurer in Chama A, ordinary member in Chama B" for
  free, using a maintained package instead of a bespoke implementation.

## Resolving "current chama"

Every authenticated web/API request that touches chama data is scoped by an explicit
`organization` route-model-bound parameter (e.g. `/chamas/{organization}/members`), never an
implicit "last selected chama" session value for anything that mutates data. A lightweight
session value may remember the *last viewed* chama purely for UX (e.g. which card to default to
on `/dashboard`), but every authorization check re-verifies against the route parameter.

## Enforcement layers (defense in depth)

1. **Route model binding** — `{organization}` resolves to an `Organization`; a non-existent or
   soft-deleted id 404s before any controller code runs.
2. **Policy check on every controller action** — `OrganizationPolicy`/`MembershipPolicy`/etc.
   verify the authenticated user has an **active** `chama_memberships` row for that
   `organization_id` (and, where relevant, a specific permission via spatie's team-scoped
   check) before any read or write. This is the authoritative check; it never trusts anything
   the client sent except the authenticated user id and the route's organization id.
3. **Query scoping** — chama-owned models use `organization_id` in every query that lists or
   loads them; as more modules are added (Phase 2+) each gets a `BelongsToOrganization` trait
   with a global scope that requires a bound organization id, so a developer cannot accidentally
   write an unscoped query that leaks cross-tenant rows.
4. **Foreign keys** — `organization_id` is a non-nullable, indexed foreign key on every
   chama-owned table, `onDelete('restrict')` by default so a chama can't be deleted out from
   under its financial history by accident.

## What this prevents

- Manipulated record IDs / direct URLs: policy check re-verifies membership regardless of what
  id is in the URL.
- API requests: the same policies run for API controllers — there's no separate, weaker auth
  path for `/api/v1/...`.
- Cross-tenant access via search, export, documents, notifications: every such feature (added in
  its own phase) must scope its query by the authenticated user's verified organization
  memberships, and this must be covered by a cross-tenant-denial feature test before it ships.

## Platform administrator access

The platform administrator (Part 22) is a separate capability (`platform-admin` gate, not a
per-chama role) and any access it grants to chama data for support purposes must be logged to
`audit_logs` with actor, organization, and reason. This is built out in Phase 7; Phase 1 only
reserves the `is_platform_admin` flag on `users` and leaves the actual admin surface for later.

## Testing requirement

Every phase that adds a chama-owned resource must add a feature test asserting that a user who
is a member of Chama A cannot read or write a resource belonging to Chama B — via the
authenticated web session and via the API — even when given a direct, otherwise-valid-looking
ID. Phase 1 establishes this pattern with `OrganizationAccessTest`.
