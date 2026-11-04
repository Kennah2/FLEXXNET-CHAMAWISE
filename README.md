# CHAMA WISE

**Save Together. Grow Together.**

A product by FlexxNet Technologies.

CHAMA WISE is a multi-tenant SaaS platform for Kenyan chamas, savings groups, merry-go-round
groups, table banking groups and investment groups to manage membership, contributions,
financial records, loans, meetings and reporting — on the web and on Android.

## Repository layout

```
chama-wise/
├── backend/   Laravel 12 application — web app + versioned REST API (/api/v1)
├── mobile/    Flutter Android application (added from Phase 5 onward)
└── docs/      Architecture, database, security, deployment and progress documentation
```

## Status

This project is under active, phased development. See `docs/DEVELOPMENT_PROGRESS.md` for the
current phase and exactly what has been implemented, and `docs/PHASE0_INSPECTION_REPORT.md` for
the initial environment/architecture inspection. Start there before picking up work.

## Documentation

- `docs/ARCHITECTURE.md` — system architecture and module layout
- `docs/DATABASE.md` / `docs/ERD.md` — schema design
- `docs/TENANCY.md` — multi-tenancy and isolation strategy
- `docs/SECURITY.md` — security and data-protection approach
- `docs/SHARED_HOSTING_DEPLOYMENT.md` / `docs/CPANEL_CONFIGURATION.md` — production deployment
  on shared cPanel hosting
- `docs/DEVELOPMENT_PROGRESS.md` — phase-by-phase build log (read this first when resuming work)

## Local development (backend)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

## Tests

```bash
cd backend
php artisan test
```

## Brand

- Primary navy `#101B35`, brand pink `#E44E83`, growth teal `#16A085`, white `#FFFFFF`, light
  grey `#F5F7FA`.
- Headings: Montserrat. Body: Inter. Financial figures: Inter Medium.
