# 01 — Laravel Backend Architecture

## 1. Stack

Use:

- Laravel
- PHP 8.x according to project environment
- MySQL 8.x or compatible
- Next.js frontend consuming REST/JSON APIs
- Queue workers for integrations and asynchronous jobs
- Scheduled tasks for SAP polling, health checks, cleanup, and reporting
- Optional Redis for queue/cache/locks if infrastructure allows
- Local file or object storage for generated documents depending on deployment

Do not use ASP.NET/C# patterns or project structures.

---

## 2. Architectural style

Use a modular, service-oriented Laravel monolith for MVP.

Recommended layers:

```text
app/
  Domain/
    Enums/
    ValueObjects/
    Services/
  Actions/
  DTO/
  Http/
    Controllers/Api/
    Requests/
    Resources/
    Middleware/
  Models/
  Policies/
  Services/
    Audit/
    Events/
    Workflow/
    Matching/
    Documents/
    Reporting/
    Security/
  Integrations/
    Sap/
    DeviceGateway/
    Printing/
    CloudSync/
  Jobs/
  Events/
  Listeners/
  Exceptions/
database/
  migrations/
  seeders/
  factories/
routes/
  api.php
tests/
  Unit/
  Feature/
```

### 2.1 Controllers

Controllers should:

- Receive HTTP requests.
- Authorize with policies/permissions.
- Validate via Form Requests.
- Call Action/Service classes.
- Return API Resources.
- Never implement workflow rules directly.

### 2.2 Actions

Use one action class per important use case, for example:

- `CreateDriverAction`
- `BlockDriverAction`
- `AssignOrderAction`
- `IdentifyGateEntryAction`
- `RegisterDriverTerminalAction`
- `ConfirmTrailerParkingAction`
- `ReleaseLoadingAction`
- `RecordAnalysisResultAction`
- `GenerateDocumentAction`
- `PrintDocumentAction`
- `EvaluateExitEligibilityAction`

Actions should run transactions where needed.

### 2.3 Domain services

Use domain services for reusable business rules:

- `OrderMatchingService`
- `VisitWorkflowService`
- `DriverEligibilityService`
- `TrailerEligibilityService`
- `StationReleaseService`
- `AnalysisDecisionService`
- `DocumentEligibilityService`
- `ExitEligibilityService`
- `ClarificationService`
- `AuditService`
- `OperationalEventService`

### 2.4 Integrations

All external systems must be behind interfaces/contracts:

- `SapConnectorInterface`
- `DeviceGatewayClientInterface`
- `GateControllerClientInterface`
- `PrinterClientInterface`
- `CardReaderClientInterface`
- `CloudSyncClientInterface`

In MVP development, implement fake/mock adapters first. Real adapters can be swapped later.

---

## 3. Request lifecycle pattern

For sensitive workflow commands:

1. Controller receives request.
2. Form Request validates payload.
3. Policy checks permission.
4. Action starts DB transaction.
5. Action loads relevant records with locks if status change is critical.
6. Domain service validates business rules.
7. Action updates records.
8. Action writes status history.
9. Action writes event log.
10. Action writes audit log if sensitive.
11. Action dispatches integration jobs if needed.
12. Action returns DTO/resource.

Example commands requiring this pattern:

- Driver block/unblock.
- Order assignment.
- Variant selection.
- Gate entry approval/denial.
- Trailer parking confirmation.
- Loading release.
- Analysis decision.
- Manual override.
- Document reprint.
- Exit gate release.
- Permission changes.

---

## 4. Laravel implementation conventions

### 4.1 IDs

The ERD uses many `char(36)` IDs. Recommended Laravel pattern:

- Use UUID strings for main business entities.
- Use `bigIncrements` for high-volume append-only logs/history where the ERD uses `bigint`.
- In models with UUID PKs:
  - `$keyType = 'string'`
  - `$incrementing = false`
  - generate UUID in model boot or action.

### 4.2 Timestamps

Use timestamp columns matching DB design:

- `created_at`
- `updated_at`
- `created_by_user_id`
- `updated_by_user_id`
- Use `timestamp(3)`/`dateTime(3)` precision where important.

### 4.3 Soft delete / archive

For master data, prefer:

- `is_active`
- `is_archived`
- status fields
- optional Laravel soft deletes only if accepted for that module

Do not hard delete operational history.

### 4.4 Enums

Use PHP enums where project version supports them, or constant classes otherwise.

Groups:

- `OrderStatus`
- `OperationStatus`
- `PlantVisitStatus`
- `TrailerStatus`
- `DriverStatus`
- `TrainingStatus`
- `AuthMediumType`
- `AnalysisType`
- `AnalysisResultStatus`
- `DocumentStatus`
- `AlertStatus`
- `Severity`
- `ClarificationStatus`
- `HardwareStatus`

### 4.5 Validation

Use Form Requests.

Validation must check:

- Required fields.
- Enum values.
- Date consistency.
- Active/not blocked states.
- Existence of referenced IDs.
- Ownership/company/site constraints.
- Reason required for sensitive actions.
- No direct edits of security-only fields.

### 4.6 API Resources

Use resources to return frontend-friendly fields, not raw DB objects.

Example:

- `DriverListResource`
- `DriverDetailResource`
- `OrderListResource`
- `PlantVisitResource`
- `EventLogResource`
- `AuditLogResource`

Resources should use readable labels when possible and include raw IDs only as route keys.

---

## 5. Security architecture

### 5.1 Authentication

Recommended for Next.js dashboard:

- Laravel Sanctum session/cookie based auth if frontend and backend are deployed in compatible domains.
- Token-based auth for device/terminal clients if needed.
- Separate service credentials for hardware/device gateway integrations.

### 5.2 Authorization

Use Laravel policies and/or permission middleware.

RBAC should support:

- Admin.
- Dispatcher.
- Operator.
- Analysis Specialist.
- Operations Manager.
- IT/Support.
- Auditor.

Possible implementation:

- `roles`
- `permissions`
- `user_roles`
- `role_permissions`

Do not rely only on frontend hiding buttons.

### 5.3 Permission examples

| Permission | Description |
|---|---|
| `drivers.view` | View drivers. |
| `drivers.create` | Create drivers. |
| `drivers.update` | Edit driver master data. |
| `drivers.block` | Block/unblock drivers. |
| `orders.assign` | Assign drivers/trailers/tractors/stations. |
| `operations.override` | Perform operational manual override. |
| `quality.decide` | Approve/reject functional quality decisions. |
| `documents.reprint` | Reprint required documents. |
| `audit.view` | View audit trail. |
| `system.configure` | Configure plant/system settings. |

---

## 6. Audit and event foundation

### 6.1 Event log

Write event log for operational facts:

- Entry requested.
- Entry denied/approved.
- Terminal login.
- Trailer registered.
- Order matched.
- Ambiguous match.
- Parking assigned/confirmed.
- Loading release denied/approved.
- Analysis result recorded.
- Document generated/printed.
- Exit denied/approved.
- Device/SAP/printer fault.

### 6.2 Audit log

Write audit log for sensitive changes:

- User/role/permission change.
- Driver/trailer/order block/unblock.
- Manual correction.
- Assignment override.
- Analysis approval/rejection.
- Print reprint after failure.
- Emergency/manual gate opening.
- Configuration changes.

### 6.3 Correlation ID

Generate or carry a `correlation_id` across:

- SAP order import.
- Order operation.
- Plant visit.
- Event log.
- Audit log.
- Integration messages.
- Alerts.

This allows tracing one business process end to end.

---

## 7. Queue and scheduler

Use jobs for:

- SAP polling/import.
- SAP outbound status feedback.
- Device health checks.
- Printer queue processing.
- Document generation if heavy.
- Cloud sync.
- Report export.
- Alert notifications.

Use scheduler for:

- SAP sync.
- Device health checks.
- Expired TAN cleanup/status update.
- Backup health check marker.
- Report generation if scheduled.
- Retention/anonymization jobs where approved.

---

## 8. Error response standard

Use structured JSON:

```json
{
  "message": "Driver cannot be unblocked.",
  "code": "DRIVER_UNBLOCK_BLOCKED_BY_LICENSE_EXPIRY",
  "details": {
    "driver_id": "uuid",
    "license_expiry_date": "2026-01-10"
  },
  "correlation_id": "uuid"
}
```

For ambiguous matches, return `409 Conflict`:

```json
{
  "message": "Multiple possible orders found. Dispatcher clarification required.",
  "code": "ORDER_MATCH_AMBIGUOUS",
  "candidates": [],
  "clarification_case_id": "uuid",
  "correlation_id": "uuid"
}
```

---

## 9. Suggested implementation modules

1. Auth/RBAC.
2. Audit/event/alert foundation.
3. Master data: companies, sites, hardware.
4. Master data: drivers, tractors, trailers.
5. Auth media: chips/TANs.
6. Orders and SAP sync stubs.
7. Plant visits and terminal workflow.
8. Parking and trailer pool.
9. Loading and device gateway stubs.
10. Quality analysis and decisions.
11. Documents/printing.
12. Exit eligibility.
13. Reports and KPIs.
14. Hardening and integration adapters.
