<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Testing and Acceptance Criteria

## Test strategy

Use automated tests for backend correctness and safety-critical rules.
Manual testing can validate UI behavior, but backend rules must be testable independently.

## Required test types

- Unit tests for services and rules.
- Feature tests for API endpoints.
- Policy tests for authorization.
- Integration-style tests for multi-entity workflows.
- Seeder/demo data checks.

## Global acceptance criteria

- All write endpoints validate inputs.
- Unauthorized users cannot perform restricted actions.
- Critical actions require reason where specified.
- Audit logs contain actor, action, entity, old/new values and reason.
- No bulk action endpoints exist unless explicitly approved.
- Hard delete is not available for audited entities.
- List endpoints support search/filter/pagination.
- Export endpoints respect visible columns.
- API responses use readable labels and semantic statuses.
- Sensitive fields are not exposed.

## Module acceptance criteria

### Drivers

- Driver list supports defined search/filters.
- Create/edit validates required fields.
- Preferred language is required.
- Block/unblock requires reason and audit.
- National ID hash is never returned.

### Customers

- Customer code is unique.
- SAP reference uniqueness is checked if provided.
- SAP-owned fields are protected.
- Block/unblock requires reason.
- Related orders/documents are available.

### Users

- Username/email validation works.
- At least one role required.
- Preferred UI language required.
- Raw passwords never returned.
- Disable/lock/reset actions audited.

### Roles & Permissions

- Critical permission changes require reason.
- Permission dependencies are validated.
- Self-lockout is prevented.
- Permission changes are audited.

### Company & Plant Configuration

- Activation blocked when required objects are missing.
- Duplicate codes are rejected.
- Activation requires confirmation.
- Active/locked configuration rejects direct structural edits.
- Change request requires before/after values and reason.

### Loading Control

- Station View returns all configured stations, including fault/offline ones.
- Active Loadings returns progress, analysis and blocker summaries.
- Wrong station/trailer/order relation returns conflict and creates clarification.
- Stale data indicator is available.

### Analysis/Documents

- NOK/invalid states block document readiness as required.
- Quality decisions require permission.
- Document generation creates print status.
- Exit is blocked until prerequisites are complete.

## Manual QA scenarios

1. Create plant configuration draft, validate, activate, then attempt direct edit.
2. Create driver, block with reason, verify audit and unavailable operational eligibility.
3. Create customer, block with reason, verify new order use is blocked.
4. Change role permissions and verify audit old/new values.
5. Simulate loading with wrong trailer chip and verify clarification.
6. Simulate printer failure and verify document/exit blocker.
