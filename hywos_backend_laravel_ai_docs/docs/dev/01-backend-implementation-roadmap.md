# 01 — Backend Implementation Roadmap for Laravel MVP

## Milestone 0 — Project foundation

Goal: prepare Laravel backend to be AI-friendly and safe for incremental development.

Tasks:

1. Create Laravel project.
2. Configure MySQL.
3. Configure environment files.
4. Add code formatting/static analysis tools if desired.
5. Add base API routing.
6. Add health endpoint.
7. Add global API error shape.
8. Add correlation ID middleware.
9. Add base test setup.
10. Add fake integration config.

Deliverables:

- Laravel runs locally.
- `/api/health` works.
- Tests run.
- Documentation folder committed.

---

## Milestone 1 — Auth, RBAC, audit/event foundation

Tasks:

1. Create users/roles/permissions migrations.
2. Implement auth.
3. Seed MVP roles and permissions.
4. Implement policy/permission middleware.
5. Create event_log table/model/service.
6. Create audit_log table/model/service.
7. Create connection_log table/model/service.
8. Add tests for auth and forbidden actions.

Deliverables:

- Login/logout/me endpoints.
- Role/permission checks.
- Audit/event services callable from actions.

---

## Milestone 2 — Base master data

Tasks:

1. Address, company, company roles.
2. Sites and plant areas.
3. Hardware objects.
4. Substances/products.
5. File assets.

Deliverables:

- CRUD APIs with filters/pagination.
- Seed sample site, gates, terminals, filling station, printer, readers.
- Tests.

---

## Milestone 3 — Drivers, tractors, trailers, auth media

Tasks:

1. Driver migration/model/API.
2. Driver block/unblock actions.
3. Tractor migration/model/API.
4. Trailer migration/model/API.
5. Auth media migration/model/API.
6. TAN create/revoke.
7. Eligibility services.
8. Driver module tests.

Deliverables:

- Next.js Driver Management module can consume APIs.
- Blocked driver denied by eligibility service.
- Sensitive fields hidden.

---

## Milestone 4 — Orders and SAP stub

Tasks:

1. Order and operation migrations.
2. Status history.
3. Fake SAP import job.
4. Order list/detail API.
5. Order assignment.
6. Process variant selection.
7. Integration message table.
8. SAP failure alert simulation.

Deliverables:

- Dispatcher can see/import/assign orders.
- Incomplete orders go to clarification.
- Assignment snapshots created.

---

## Milestone 5 — Plant visits and terminal workflows

Tasks:

1. Plant visit migrations.
2. Visit authentication and step execution.
3. Gate entry identification endpoint.
4. Driver terminal login endpoint.
5. Registration endpoint.
6. Action selection endpoint.
7. Confirm/correction endpoint.
8. Order matching service.
9. Clarification cases.

Deliverables:

- Driver entry and terminal flow can be simulated with API.
- Ambiguous matches create clarification.
- No auto-guessing.

---

## Milestone 6 — Parking and trailer pool

Tasks:

1. Parking space migration/API.
2. Parking event migration.
3. Assign parking.
4. Confirm parking.
5. Confirm pickup.
6. Variant A support.
7. Variant C/D pickup support.

Deliverables:

- Trailer parking and pickup are traceable.
- Parking space statuses update correctly.

---

## Milestone 7 — Loading and device gateway stub

Tasks:

1. Loading operation migration.
2. Loading measurement migration.
3. Fake device gateway client.
4. Panel login endpoint.
5. Release loading action.
6. Start/complete loading endpoints.
7. Device event endpoint.

Deliverables:

- Loading release enforces all prerequisites.
- Loading can be simulated without real PLC.
- Device failures create alerts.

---

## Milestone 8 — Quality analysis

Tasks:

1. Quality parameter/specification migrations.
2. Quality analysis/detail migrations.
3. Pre-analysis action.
4. Main-analysis action.
5. Analysis decision/approval action.
6. Analysis Specialist permissions.
7. Tests for all analysis decision rules.

Deliverables:

- Pre-analysis max 3 attempts.
- Main technical invalid one repeat.
- Main functionally NOK blocks documents/exit.
- Specialist approval is audited.

---

## Milestone 9 — Documents, printing, exit

Tasks:

1. Document migration.
2. Print job migration.
3. Certificate relation.
4. Document generation action.
5. Print/reprint actions.
6. Exit eligibility service.
7. Exit gate endpoint.
8. Tests for exit blockers.

Deliverables:

- Mandatory documents block/allow exit correctly.
- Print failure creates alert.
- Exit approved closes visit.

---

## Milestone 10 — Reporting, hardening, integration readiness

Tasks:

1. KPI query endpoints.
2. Export jobs.
3. Connection health dashboard APIs.
4. OpenAPI documentation.
5. Integration adapter replacement plan.
6. Performance indexes.
7. Backup/restore checklist.
8. UAT test scripts.

Deliverables:

- MVP backend ready for frontend integration and hardware/SAP adapter implementation.
