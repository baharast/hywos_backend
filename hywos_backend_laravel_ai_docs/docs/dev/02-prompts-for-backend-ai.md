<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Prompts for Backend AI Assistants

Use these prompts with Claude, Copilot or another AI coding tool.

## Prompt 1 — Read-only inspection

```text
You are a senior Laravel backend developer.
Inspect the current repository only. Do not modify files.
Use AGENTS.md and docs/ as the source of truth.
Report:
1. Existing Laravel structure.
2. Existing migrations/models/controllers/routes.
3. Missing pieces compared to the requested module.
4. Risks and inconsistencies.
5. Exact files that would need to be created/updated.
Do not write code yet.
```

## Prompt 2 — Implement a module safely

```text
You are a senior Laravel backend developer.
Implement the [MODULE NAME] backend according to:
- AGENTS.md
- docs/modules/[MODULE FILE]
- docs/technical/03-api-design-contracts.md
- docs/technical/04-auth-rbac-security-audit.md

Rules:
- Use Form Requests, Policies, API Resources and Services.
- Keep controllers thin.
- Add migrations/models/controllers/resources/requests/policies/services/routes/tests.
- Add audit/event behavior for critical actions.
- No bulk actions.
- No hard delete.
- Do not invent fields not required by the docs unless you clearly mark them as assumptions.

Return a concise implementation summary and list changed files.
```

## Prompt 3 — Implement Drivers CRUD

```text
Implement Driver Management backend in Laravel.
Use docs/modules/01-driver-management-backend.md.
Required endpoints:
GET /api/drivers
POST /api/drivers
GET /api/drivers/{driver}
PUT /api/drivers/{driver}
POST /api/drivers/{driver}/block
POST /api/drivers/{driver}/unblock
POST /api/drivers/export

Include search/filter/pagination, status resources, block/unblock reason, audit log, no national_id_hash in responses and feature tests.
```

## Prompt 4 — Implement Users and Roles

```text
Implement dashboard Users and Roles & Permissions backend in Laravel.
Use docs/modules/users.md and docs/modules/07-roles-permissions-backend.md.
Backend must enforce permissions and prevent self-lockout.
Critical permission changes require reason and audit old/new values.
Do not expose raw passwords.
```

## Prompt 5 — Implement Company & Plant Configuration

```text
Implement Company & Plant Configuration backend lifecycle in Laravel.
Use docs/modules/08-company-plant-configuration-backend.md.
Support draft setup, validation, review, activation lock and change requests after activation.
Direct editing of active/locked structural objects must be rejected.
Add tests for activation validation and post-activation change request behavior.
```

## Prompt 6 — API compatibility review

```text
Review all implemented APIs against docs/context/01-frontend-ux-context.md and docs/technical/03-api-design-contracts.md.
Check if frontend list pages can support overview cards, filters, table rows, row actions, pagination and visible-column export.
Report missing fields, inconsistent status names and authorization gaps.
```
