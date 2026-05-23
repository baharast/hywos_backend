<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# HYWOS / FillTrack Backend Laravel AI Documentation

This folder is the backend onboarding and implementation guide for the HYWOS / FillTrack MVP.
It is designed for Laravel backend developers, AI coding agents, technical reviewers, and new team members who need to understand the project before touching code.

## Current update basis

This package was rebuilt and aligned with the latest FillTrack Markdown onboarding pack located in `source/filltrack_md_onboarding/`.
The latest frontend/source documents included in this package are:

- `FillTrack Company Plant Configuration UX Frontend Spec-EN-V3.md`
- `FillTrack Core UI Components Frontend Rules-EN-V1.md`
- `FillTrack Customers UX Frontend Spec-EN-V2.md`
- `FillTrack Dashboard Shell Template Frontend Rules-EN-V3.md`
- `FillTrack Driver Management UX Frontend Spec -EN-V2.md`
- `FillTrack Loading Control UX Frontend Spec-EN-V2.md`
- `FillTrack Mvp Design System Frontend Rules-EN-V3.1.md`
- `FillTrack Roles Permissions UX Frontend Spec-EN-V1.md`
- `FillTrack Users UX Frontend Spec-EN-V1.md`
- `README_ONBOARDING_ORDER.md`

## Backend technology decision for this package

The backend implementation target for this documentation package is:

- **Laravel** for the backend API and application services.
- **MySQL** for persistent relational data.
- **Next.js** frontend consuming REST-style JSON APIs.
- Queue/jobs for asynchronous sync, print, integration and notification work.
- Audit-first backend behavior for sensitive operational, configuration and access changes.

Some older functional source documents mention ASP.NET. For this package, treat those mentions as legacy context only. Do **not** generate ASP.NET code from this documentation.

## How to use this folder with an AI coding assistant

1. Start with `AGENTS.md`.
2. Read `docs/context/00-project-ai-context.md` and `docs/context/01-frontend-ux-context.md`.
3. Read the module-specific backend document before implementing a feature.
4. Read the technical documents before changing architecture, database, APIs, RBAC or integration logic.
5. Use prompts from `docs/dev/02-prompts-for-backend-ai.md` for controlled implementation tasks.

## Recommended reading order

1. `AGENTS.md`
2. `docs/context/00-project-ai-context.md`
3. `docs/context/01-frontend-ux-context.md`
4. `docs/context/02-source-documents-map.md`
5. `docs/product/01-mvp-scope-domain.md`
6. `docs/product/02-process-flows-business-rules.md`
7. `docs/technical/01-laravel-backend-architecture.md`
8. `docs/technical/02-database-model-mysql.md`
9. `docs/technical/03-api-design-contracts.md`
10. Relevant files under `docs/modules/`

## Folder structure

```text
hywos_backend_laravel_ai_docs/
├── AGENTS.md
├── README.md
├── CHANGELOG_UPDATED_FROM_FILLTRACK_MD.md
├── docs/
│   ├── context/
│   ├── product/
│   ├── technical/
│   ├── modules/
│   ├── dev/
│   └── qa/
└── source/
    └── filltrack_md_onboarding/
```

## Non-negotiable MVP rules

- No bulk actions in MVP.
- No hard delete for operational, master-data, document, user/access or configuration entities.
- No auto-guessing when driver, trailer, order, station or plant-visit matching is ambiguous.
- Critical actions require reason capture and audit logging.
- Activated Company & Plant Configuration is locked/restricted; structural changes require controlled change request.
- Export APIs must respect visible columns and active filters where relevant.
- Backend must enforce permissions; frontend hiding/disable logic is not sufficient security.
- All data shown in the UI should use readable labels, not raw database IDs as primary display values.
