<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Authentication, RBAC, Security and Audit

## Authentication

Use Laravel authentication appropriate to the project setup.
For API-first development, Laravel Sanctum is a reasonable MVP choice unless the team chooses another auth layer.

Never expose raw passwords or tokens in API responses.

## Users vs Drivers

Users are internal dashboard accounts.
Drivers are operational people who interact with gate/driver/filling-station/exit terminals.

Do not merge these concepts.

## Roles & Permissions

Roles are access groups for dashboard users.
Permissions define page, data and action rights.

Minimum permission groups:

- Dashboard
- Operations
- Orders
- Analysis & Quality
- Documents & Reports
- Alarms & Events
- Master Data
- System & Devices
- Administration

Permission types:

- Page access.
- Action permission.
- Data permission.
- Critical permission.
- Administration permission.

## Backend enforcement

Frontend must hide or disable unavailable actions, but backend must enforce all permission checks.

Use:

- Policies for entity-specific authorization.
- Gates for broad permissions.
- Middleware for route-level restrictions where useful.

## Critical permissions

Critical permissions include actions that affect:

- quality decisions,
- loading release/block,
- manual overrides,
- block/unblock master data,
- role/permission management,
- user disabling/locking,
- plant configuration activation/change,
- audit visibility/export.

Critical permission changes must require confirmation/reason and audit logging.

## Self-lockout prevention

Backend must prevent:

- disabling the only active admin/user-access manager,
- removing all roles that allow user/role management,
- deactivating the last active role with Administration → User & Access rights,
- a user disabling or locking themselves if no other admin remains.

Frontend validation is helpful but not sufficient.

## Audit log requirements

Audit logs must capture:

- actor user id,
- actor display name if denormalized,
- entity type,
- entity id,
- action,
- old values,
- new values,
- reason if required,
- source module/action,
- timestamp,
- request metadata if allowed.

## Event log requirements

Event logs capture operational and system happenings:

- gate identification,
- terminal session started,
- station status changed,
- loading started/completed,
- analysis result received,
- SAP sync failed,
- printer job failed,
- exit blocked/allowed.

## Sensitive data

Do not expose:

- raw passwords,
- tokens,
- national ID hash,
- secret integration credentials,
- raw PLC credentials,
- internal database IDs as primary user-facing identifiers.

## Preferred language

Users have preferred UI language. Drivers have preferred terminal/message language.
The default language in current specs is German where no better preference exists.
