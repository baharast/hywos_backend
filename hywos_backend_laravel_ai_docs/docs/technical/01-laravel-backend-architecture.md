<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Laravel Backend Architecture

## Architectural style

Use a modular Laravel architecture with clear boundaries between HTTP, validation, authorization, process logic, persistence, integration and audit.

Recommended directory pattern:

```text
app/
├── Http/
│   ├── Controllers/Api/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Services/
│   ├── Drivers/
│   ├── Customers/
│   ├── Loading/
│   ├── PlantConfiguration/
│   ├── Users/
│   ├── RolesPermissions/
│   ├── Audit/
│   └── Integrations/
├── Actions/
├── Enums/
├── Events/
├── Listeners/
└── Jobs/
```

## Controller rule

Controllers should be thin:

- authorize,
- validate via Form Request,
- call service/action,
- return API Resource.

Do not place process rules directly in controllers.

## Service layer

Use services for:

- matching driver/trailer/order/station,
- plant visit transitions,
- loading operation state changes,
- analysis result handling,
- document readiness checks,
- plant configuration validation/activation,
- user/role critical access changes,
- SAP/device integration orchestration.

## Transactions

Use database transactions whenever changing more than one entity or when a state transition must be atomic.

Examples:

- Block driver + create audit log + create event.
- Activate plant configuration + lock version + create audit logs.
- Start loading + update station state + create loading event.
- Generate document + create print job + update document status.

## Events and audit

Use Laravel Events/Listeners for non-blocking side effects, but keep critical transaction consistency in the service before returning success.

Example pattern:

```php
DB::transaction(function () use ($driver, $reason, $actor) {
    $old = $driver->only(['status', 'block_reason']);
    $driver->block($reason, $actor);
    AuditLog::recordChange($actor, $driver, 'driver.blocked', $old, $driver->fresh()->toArray(), $reason);
    EventLog::recordOperational('driver.blocked', $driver, $actor);
});
```

## API Resources

Use API Resources to shape responses for frontend needs:

- readable labels,
- status labels and tones,
- relation summaries,
- action permissions,
- tab-friendly nested summaries.

## Validation

Use Form Requests for every write endpoint.
Examples:

- `StoreDriverRequest`
- `UpdateDriverRequest`
- `BlockDriverRequest`
- `StoreCustomerRequest`
- `ActivatePlantConfigurationRequest`
- `UpdateRolePermissionsRequest`

## Authorization

Use Policies/Gates. Backend must enforce:

- view,
- create,
- update,
- block/unblock,
- export,
- approve/activate,
- manage roles/permissions,
- view audit logs.

## Jobs and queues

Use queued jobs for:

- SAP sync/import/export.
- OPC UA/device polling or event processing where applicable.
- PDF generation/print queue.
- export generation.
- notification dispatch.

## Error behavior

Return operationally useful errors:

- `422` validation errors with field keys.
- `403` authorization denial.
- `409` business conflict such as ambiguous assignment or active locked configuration.
- `423` locked resource where appropriate.
- `503` integration unavailable / degraded state.

Avoid generic errors for operational blockers. The frontend needs actionable messages.
