# 02 — Prompts for Backend AI Coding Assistants

## Prompt 1 — Understand backend context

```text
Read AGENTS.md and docs/context/00-project-ai-context.md.

Important: the backend is Laravel + MySQL, not ASP.NET.

Summarize:
1. What the system does.
2. Main domain objects.
3. Critical safety/business rules.
4. Which modules should be implemented first.
5. Which open questions must not be guessed.

Do not code yet.
```

---

## Prompt 2 — Plan Laravel architecture

```text
Read docs/technical/01-laravel-backend-architecture.md and docs/technical/02-database-model-mysql.md.

Create a Laravel backend architecture plan for the MVP.

Return:
1. Folder structure.
2. Main service/action classes.
3. Main Eloquent models.
4. Migration groups and recommended order.
5. Auth/RBAC approach.
6. Audit/event logging approach.
7. Testing approach.

Do not generate code yet.
```

---

## Prompt 3 — Implement foundation

```text
Implement Milestone 0 and Milestone 1 from docs/dev/01-backend-implementation-roadmap.md.

Requirements:
- Laravel + MySQL.
- API health endpoint.
- Correlation ID middleware.
- Structured API error response.
- Auth endpoints.
- User/role/permission migrations.
- Event log and audit log migrations/services.
- Seed roles and permissions.
- Feature tests for login, permissions, audit/event service.

Before coding, list files you will create or modify.
```

---

## Prompt 4 — Implement Driver Management backend

```text
Read docs/modules/01-driver-management-backend.md and docs/technical/03-api-design-contracts.md.

Implement Driver Management backend.

Requirements:
- Driver migration/model.
- Recommended MVP fields including block/training fields.
- Driver list/detail/create/update endpoints.
- Block/unblock endpoints with required reason.
- Driver auth media summary.
- Driver visits/events/audit endpoints.
- Form requests.
- API resources.
- Policies/permissions.
- Audit/event logs for sensitive actions.
- Tests.
- Do not expose national_id_hash.
- Do not add bulk actions.
```

---

## Prompt 5 — Implement Orders and Plant Visits

```text
Read docs/modules/02-orders-dispatching-plant-visits.md.

Implement:
- order_header, order_operation, status histories.
- fake SAP import.
- order list/detail APIs.
- assignment and process variant selection.
- plant_visit, visit_authentication, visit_step_execution.
- gate entry identification.
- driver terminal login.
- terminal registration.
- selected action and context confirmation.
- order matching service.
- clarification cases.

Critical rule:
If multiple matches exist, return conflict and create clarification. Do not auto-guess.
```

---

## Prompt 6 — Implement Loading, Analysis, Documents and Exit

```text
Read docs/modules/03-loading-analysis-documents.md.

Implement backend services and APIs for:
- filling station panel login,
- loading operation,
- loading release,
- loading measurement/completion,
- pre-analysis,
- main-analysis,
- analysis specialist approval,
- documents,
- print jobs,
- exit eligibility,
- exit gate decision.

Critical rules:
- Loading requires active order and valid assignment.
- Pre-analysis has max three attempts.
- Main technical invalid allows one technical repeat.
- Main functionally NOK blocks documents/exit unless Analysis Specialist approval.
- Mandatory documents must be generated/provided before exit.
```

---

## Prompt 7 — Review generated backend code

```text
Review the current Laravel backend code against the docs.

Check:
1. Does any controller contain business workflow logic that should be in a service/action?
2. Are all sensitive actions audited?
3. Are operational events logged?
4. Are statuses controlled by enums?
5. Are ambiguous matches blocked instead of guessed?
6. Are Driver, Trailer, Order, Plant Visit, Analysis, Document, Exit rules enforced?
7. Are security-only fields hidden?
8. Are tests covering both allowed and denied paths?

Return a prioritized fix list.
```
