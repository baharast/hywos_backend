<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Backend Implementation Roadmap

This roadmap is optimized for Laravel + MySQL MVP delivery aligned with the latest frontend documentation.

## Phase 0 — Foundation

- Create Laravel project structure.
- Configure database, environment, queues and logging.
- Add authentication foundation.
- Add base API response conventions.
- Add base audit/event models and services.
- Add common enums/status patterns.

## Phase 1 — Access Control

- Users.
- Roles.
- Permissions.
- Policies/Gates.
- Self-lockout prevention.
- Critical permission audit logging.

Reason: frontend modules and critical actions depend on backend permission enforcement.

## Phase 2 — Company & Plant Configuration

- Draft plant setup.
- Areas, gates, terminals/panels, bay lines, parking areas/spaces.
- Validation.
- Review/activate lock.
- Change request flow.

Reason: operations depend on configured plant topology.

## Phase 3 — Master Data Core

- Drivers.
- Customers.
- Trailers and tractor units/vehicles.
- Chip cards and TANs.
- Block/unblock flows.
- Search/filter/export.

## Phase 4 — Orders and Plant Visits

- Loading order import/mock import.
- Plant visit lifecycle.
- Driver/trailer/order matching.
- Clarification cases.
- Terminal/gate event endpoints.

## Phase 5 — Loading Control

- Station View data endpoint.
- Active Loadings list.
- Loading detail.
- Station/device/analysis/alarm summaries.

## Phase 6 — Analysis, Quality and Documents

- Pre-analysis/main-analysis records.
- Quality decisions.
- Document generation states.
- Print job status.
- Exit eligibility.

## Phase 7 — Integrations

- SAP sync scaffolding.
- OPC UA/PLC data ingestion abstraction.
- Gate/terminal/panel/printer interface health.
- Integration sync logs and retries.

## Phase 8 — Testing and Hardening

- Feature tests per module.
- Policy tests.
- Audit tests.
- Integration failure tests.
- Seeders for demo data.
- API docs / OpenAPI export if chosen.

## MVP priority notes

Start with backend capabilities that unblock frontend implementation:

1. Auth/current user/permissions.
2. Users/Roles endpoints.
3. Plant configuration read/write/activation endpoints.
4. Drivers and Customers list/detail/create/edit/block.
5. Loading Control mock-compatible endpoints.

Do not start with low-level PLC implementation before process/domain APIs are shaped.
