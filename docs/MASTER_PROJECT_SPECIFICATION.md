# CHAMA WISE — Master Project Specification (Reference)

This file is the canonical pointer to the full product brief that governs this build. The
complete, verbatim specification ("FINAL MASTER SOFTWARE DEVELOPMENT PROMPT — Complete Chama
Management SaaS Platform") was supplied by the project owner (FlexxNet Technologies) at the
start of development and covers product identity, branding, technical stack, multi-tenancy,
every functional module, security, database design, testing, performance, documentation,
modular expansion, the phased roadmap, and strict development rules (Parts 1–38).

Rather than duplicating all 38 parts verbatim here (and risking the copy drifting out of sync),
this document summarizes the parts that most directly govern day-to-day implementation decisions
and points to the dedicated doc for each. Treat the original brief as authoritative; these docs
are its working-level elaboration as each phase is built.

## Identity (Part 2)

- Product name: **CHAMA WISE** (always two words — never "FLEXXNET-CHAMAWISE" as a product name;
  that string is only the GitHub repository name).
- Parent company: FlexxNet Technologies.
- Tagline: "Save Together. Grow Together."
- Brand colours: navy `#101B35`, pink `#E44E83`, teal `#16A085`, white `#FFFFFF`, grey `#F5F7FA`.
- Fonts: Montserrat (headings), Inter (body), Inter Medium (financial figures).

## Technical stack (Part 4)

Laravel 12 / PHP 8.3 / MySQL 8 backend, Blade + Tailwind + Alpine + Vite web frontend, Flutter
Android app, versioned Sanctum-secured REST API, Pest/PHPUnit + Flutter tests, initial production
hosting on shared cPanel hosting.

## Architecture, tenancy, database, security

See `docs/ARCHITECTURE.md`, `docs/TENANCY.md`, `docs/DATABASE.md`, `docs/SECURITY.md`.

## Roadmap (Part 35)

Phase 0 (inspection) → 1 (foundation) → 2 (savings MVP) → 3 (financial integrity) →
4 (payments/Daraja) → 5 (Android) → 6 (advanced modules: merry-go-round, loans, meetings,
investments) → 7 (SaaS administration/billing) → 8 (production readiness). Tracked in
`docs/DEVELOPMENT_PROGRESS.md`.

## Strict development rules (Part 36) — non-negotiable throughout

1. Inspect existing code before editing; preserve working functionality; no unnecessary
   rewrites or duplicate implementations.
2. No hardcoded chama business rules, subscription prices, or financial balances.
3. Never use floating-point arithmetic for money.
4. Never delete posted financial history — correct via reversal/adjustment.
5. Never bypass tenant authorization or trust client-supplied tenant IDs/financial calculations.
6. Never expose payment credentials, fabricate M-Pesa success, or treat an STK Push request as a
   completed payment.
7. Never expose private documents; never ship dead buttons or placeholder critical functionality.
8. Migrations for all schema changes; server-side validation always.
9. Test significant features; never claim a test passed without running it.
10. Maintain documentation; explain architectural decisions; report compatibility problems
    honestly instead of silently working around them.
11. Keep all financial operations auditable.

## Custody limitation

The platform records and verifies payments; it does not take custody of pooled group funds
without a separate legal/regulatory/financial-architecture review (Part 28).
