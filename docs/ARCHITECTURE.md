# CHAMA WISE — Architecture

## Overview

CHAMA WISE is a modular monolith: one Laravel 12 application, one MySQL 8 database (SQLite for
local development and automated tests), serving both a server-rendered web application (Blade +
Tailwind CSS + Alpine.js, built with Vite) and a versioned JSON REST API
(`/api/v1/...`, Laravel Sanctum) consumed by the Flutter Android app. There is no separate
microservice tier at this stage — see Part 5 of the product brief for the rationale (shared
hosting, small team, avoid premature distributed-systems complexity).

```
Web (Blade/Tailwind/Alpine)   Android (Flutter)
            \                       /
             \                     /
              Versioned REST API (/api/v1)
                       |
              Laravel Application
                       |
              Business Services (app/Domain/*)
                       |
                 MySQL 8 Database
```

The backend is the single source of truth for every business rule, financial calculation and
authorization decision. The Flutter client never re-implements financial logic; it only renders
what the API returns and submits requests for the API to validate.

## Directory layout

```
chama-wise/
├── backend/            Laravel 12 application (this is where almost all Phase 1 work lives)
│   ├── app/
│   │   ├── Models/
│   │   ├── Http/Controllers/        Web controllers
│   │   ├── Http/Controllers/Api/V1  API controllers (added from Phase 2+ once there's data to expose)
│   │   ├── Policies/                 Tenant-aware authorization
│   │   ├── Services/                 Business services (domain logic, not fat controllers)
│   │   └── Support/                  Cross-cutting helpers (audit logging, tenant context, …)
│   ├── database/migrations
│   ├── database/seeders
│   ├── resources/views
│   ├── routes/web.php, routes/api.php
│   └── tests/Feature, tests/Unit
├── mobile/              Flutter app (created in Phase 5)
├── docs/                Documentation (this folder)
└── README.md
```

Domains (Part 5 of the brief) are implemented as modules under `app/` grouped by concern
(`Models/Tenancy`, `Models/Contributions`, etc. as they're introduced) rather than as separate
packages — a modular monolith, not a package-per-domain split, to keep Phase 1–4 velocity high
on a small shared-hosting target. If the product later needs stricter module boundaries, the
namespaces are already domain-grouped, so extracting them is a refactor, not a rewrite.

## Multi-tenancy

See `docs/TENANCY.md` for the full design. In short: every chama is an `Organization` row;
every chama-owned table carries `organization_id`; access is only ever granted through a
verified, authenticated membership — never a client-supplied tenant ID.

## Financial ledger

Deferred to Phase 3. Will use a double-entry journal, DECIMAL/minor-unit columns, and
append-only posted transactions (corrections via reversal, never destructive edit). Documented
in `docs/FINANCIAL_LEDGER.md` when implemented.

## Why these choices

- **Blade + Tailwind + Alpine, not a SPA framework**: matches the confirmed stack (Part 4),
  keeps the web app simple to host on shared cPanel hosting, and avoids a separate frontend
  build/deploy pipeline.
- **Sanctum over Passport**: the API has first-party clients only (own web app via cookies, own
  Flutter app via personal access tokens) — Sanctum is the right-sized tool; Passport's OAuth2
  server is unnecessary complexity here.
- **spatie/laravel-permission (team-scoped) for roles**: Part 10 requires per-chama roles for
  the same user account, and configurable/custom roles. Spatie's "teams" feature does exactly
  this (`team_id` = `organization_id`) and is a well-maintained, widely used package rather than
  a bespoke RBAC implementation — this is "avoid unnecessary dependencies" cutting in favor of
  reuse, not against it.
- **Database queue driver**: shared cPanel hosting usually has no persistent worker process;
  `database` queue + cron-triggered `queue:work --stop-when-empty` (documented in
  `docs/CPANEL_CONFIGURATION.md` once written) is the portable choice.
