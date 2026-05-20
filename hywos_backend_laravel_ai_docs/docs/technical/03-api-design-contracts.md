# 03 — API Design and Contracts

## 1. API principles

The Laravel backend exposes REST/JSON APIs for the Next.js frontend and terminals.

Rules:

- Prefix with `/api`.
- Use plural resource names.
- Use UUID route keys for business entities.
- Use pagination for list endpoints.
- Use filter query parameters.
- Return structured validation and domain errors.
- Do not expose raw sensitive fields.
- Use API Resources.
- Use actions for workflow commands.
- Every workflow command returns the updated entity state or a command result with correlation ID.

---

## 2. Standard response shape

### 2.1 List response

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 100,
    "last_page": 4
  },
  "links": {}
}
```

### 2.2 Detail response

```json
{
  "data": {
    "id": "uuid"
  }
}
```

### 2.3 Command response

```json
{
  "data": {
    "id": "uuid",
    "status": "updated_status"
  },
  "message": "Command completed.",
  "correlation_id": "uuid"
}
```

### 2.4 Error response

```json
{
  "message": "The selected trailer does not match the order.",
  "code": "TRAILER_ORDER_MISMATCH",
  "details": {
    "order_id": "uuid",
    "trailer_id": "uuid"
  },
  "correlation_id": "uuid"
}
```

---

## 3. Auth and session APIs

### POST `/api/auth/login`

Request:

```json
{
  "username": "operator@example.com",
  "password": "secret"
}
```

Response includes user profile and permissions.

### POST `/api/auth/logout`

Logout current user.

### GET `/api/auth/me`

Returns:

- user ID
- name
- email
- roles
- permissions
- preferred culture
- company
- UI/session flags

---

## 4. Users, roles, permissions

### GET `/api/users`

Filters:

- `search`
- `status`
- `role`
- `company_id`

### POST `/api/users`

Create user.

### GET `/api/users/{userId}`

User detail.

### PATCH `/api/users/{userId}`

Update user.

### POST `/api/users/{userId}/lock`

Requires reason.

### POST `/api/users/{userId}/unlock`

Requires reason.

### GET `/api/roles`
### POST `/api/roles`
### GET `/api/permissions`
### POST `/api/roles/{roleId}/permissions`

All role/permission changes require audit.

---

## 5. Master data APIs

### 5.1 Companies

```text
GET    /api/companies
POST   /api/companies
GET    /api/companies/{companyId}
PATCH  /api/companies/{companyId}
POST   /api/companies/{companyId}/activate
POST   /api/companies/{companyId}/deactivate
```

Filters:

- `search`
- `role`
- `is_active`
- `sap_code`
- `country`

### 5.2 Drivers

```text
GET    /api/drivers
POST   /api/drivers
GET    /api/drivers/{driverId}
PATCH  /api/drivers/{driverId}
POST   /api/drivers/{driverId}/block
POST   /api/drivers/{driverId}/unblock
GET    /api/drivers/{driverId}/auth-media
GET    /api/drivers/{driverId}/plant-visits
GET    /api/drivers/{driverId}/events
GET    /api/drivers/{driverId}/audit
POST   /api/drivers/{driverId}/tans
```

List filters:

- `search`
- `status`
- `training_status`
- `identification_status`
- `language`
- `validity_attention`
- `employer_company_id`
- `operator_company_id`
- `last_visit_from`
- `last_visit_to`

Create/update request fields:

```json
{
  "driver_code": "DRV-1001",
  "first_name": "Max",
  "last_name": "Mustermann",
  "national_id_last4": "1234",
  "license_no": "L-123",
  "license_expiry_date": "2027-12-31",
  "phone": "+49...",
  "email": "max@example.com",
  "preferred_culture_code": "de-DE",
  "employer_company_id": "uuid",
  "operator_company_id": "uuid",
  "training_status_code": "valid",
  "is_active": true,
  "notes": "optional"
}
```

Block request:

```json
{
  "reason_code": "SAFETY_BLOCK",
  "reason_note": "Training expired."
}
```

Rules:

- `national_id_hash` is never accepted or returned through standard dashboard API.
- Blocking/unblocking always requires reason and audit log.
- Blocked overrides active status visually and operationally.

### 5.3 Tractors

```text
GET    /api/tractors
POST   /api/tractors
GET    /api/tractors/{tractorId}
PATCH  /api/tractors/{tractorId}
POST   /api/tractors/{tractorId}/block
POST   /api/tractors/{tractorId}/unblock
GET    /api/tractors/{tractorId}/plant-visits
```

### 5.4 Trailers

```text
GET    /api/trailers
POST   /api/trailers
GET    /api/trailers/{trailerId}
PATCH  /api/trailers/{trailerId}
POST   /api/trailers/{trailerId}/block
POST   /api/trailers/{trailerId}/unblock
GET    /api/trailers/{trailerId}/plant-visits
GET    /api/trailers/{trailerId}/parking-events
GET    /api/trailers/{trailerId}/quality-history
```

### 5.5 Authentication media

```text
GET    /api/auth-media
POST   /api/auth-media
GET    /api/auth-media/{authMediumId}
PATCH  /api/auth-media/{authMediumId}
POST   /api/auth-media/{authMediumId}/block
POST   /api/auth-media/{authMediumId}/revoke
POST   /api/tans
POST   /api/tans/{authMediumId}/revoke
```

TAN create request:

```json
{
  "driver_id": "uuid",
  "order_id": "uuid",
  "expires_at": "2026-06-01T12:00:00Z",
  "is_single_use": true,
  "reason_note": "Fallback for missing chip."
}
```

---

## 6. Orders and dispatching APIs

### 6.1 Orders

```text
GET    /api/orders
POST   /api/orders
GET    /api/orders/{orderId}
PATCH  /api/orders/{orderId}
POST   /api/orders/{orderId}/cancel
GET    /api/orders/{orderId}/status-history
GET    /api/orders/{orderId}/operations
```

Manual `POST /api/orders` remains subject to open question. If allowed, it must be clearly marked as local/manual and auditable.

### 6.2 Assign order

```text
POST /api/orders/{orderId}/assign
```

Request:

```json
{
  "driver_id": "uuid",
  "tractor_id": "uuid",
  "trailer_id": "uuid",
  "forwarder_company_id": "uuid",
  "site_id": "uuid",
  "filling_station_hardware_id": "uuid",
  "parking_space_id": "uuid",
  "planned_start": "2026-06-01T08:00:00Z",
  "planned_end": "2026-06-01T10:00:00Z",
  "process_variant_code": "variant_b",
  "reason_note": "Dispatcher assignment."
}
```

Rules:

- Validate all referenced entities.
- Check blocked/expired states.
- Preserve assignment snapshot.
- Audit all changes.

### 6.3 Select process variant

```text
POST /api/order-operations/{operationId}/select-variant
```

Request:

```json
{
  "process_variant_code": "variant_a",
  "parking_space_id": "uuid",
  "filling_station_hardware_id": "uuid",
  "reason_note": "Trailer to be parked before later pickup."
}
```

---

## 7. Plant visits and terminal workflow APIs

### 7.1 Plant visits

```text
GET    /api/plant-visits
GET    /api/plant-visits/{plantVisitId}
GET    /api/plant-visits/{plantVisitId}/steps
GET    /api/plant-visits/{plantVisitId}/events
GET    /api/plant-visits/{plantVisitId}/audit
```

### 7.2 Entry gate identification

```text
POST /api/terminal/gate/entry-identify
```

Request:

```json
{
  "hardware_object_id": "entry-gate-uuid",
  "identifier": "chip-or-tan-value",
  "identifier_type": "chip_card",
  "occurred_at": "2026-06-01T08:00:00Z"
}
```

Response:

```json
{
  "decision": "approved",
  "message": "Welcome",
  "message_culture": "de-DE",
  "plant_visit_id": "uuid",
  "correlation_id": "uuid"
}
```

Denied response still returns a structured body and logs event.

### 7.3 Driver terminal login

```text
POST /api/terminal/driver/login
```

Request:

```json
{
  "hardware_object_id": "terminal-uuid",
  "identifier": "chip-or-tan-value",
  "identifier_type": "chip_card"
}
```

### 7.4 Register vehicle/trailer context

```text
POST /api/terminal/driver/{plantVisitId}/registration
```

Request:

```json
{
  "has_trailer": true,
  "trailer_chip_identifier": "optional",
  "trailer_license_plate": "SW-FT-100",
  "tractor_license_plate": "SW-TR-200"
}
```

### 7.5 Select driver action

```text
POST /api/terminal/driver/{plantVisitId}/select-action
```

Request:

```json
{
  "action_code": "loading"
}
```

Response:

- next instruction,
- matched order/operation/trailer if unique,
- or clarification/conflict if ambiguous.

### 7.6 Confirm displayed data

```text
POST /api/terminal/driver/{plantVisitId}/confirm-context
```

Request:

```json
{
  "confirmed": true,
  "correction_reason": null
}
```

If `confirmed=false`, create clarification case.

---

## 8. Parking and pickup APIs

### GET `/api/parking-spaces`

Filters:

- `site_id`
- `plant_area_id`
- `status`
- `trailer_id`

### POST `/api/plant-visits/{plantVisitId}/assign-parking`

Dispatcher/operator action.

### POST `/api/plant-visits/{plantVisitId}/confirm-parking`

Request:

```json
{
  "parking_space_id": "uuid",
  "trailer_id": "uuid",
  "confirmation_source_code": "terminal",
  "hardware_object_id": "uuid",
  "reason_note": "Manual confirmation because reader unavailable."
}
```

### POST `/api/plant-visits/{plantVisitId}/confirm-pickup`

Request:

```json
{
  "trailer_id": "uuid",
  "tractor_id": "uuid",
  "confirmation_source_code": "reader",
  "hardware_object_id": "uuid"
}
```

---

## 9. Filling station and loading APIs

### POST `/api/filling-station/panel/login`

Validates driver/trailer/order/station assignment.

### POST `/api/loading-operations/{loadingOperationId}/release`

Only after assignment and pre-analysis prerequisites.

### POST `/api/loading-operations/{loadingOperationId}/start`

From device adapter/operator if allowed.

### POST `/api/loading-operations/{loadingOperationId}/measurements`

From device gateway adapter.

### POST `/api/loading-operations/{loadingOperationId}/complete`

Records actual quantity and completion.

Rules:

- Use device gateway/service identity for automated device updates.
- Do not accept panel release if assignment mismatch exists.
- PLC/device communication failure blocks automatic release.

---

## 10. Quality analysis APIs

```text
GET    /api/quality/analyses
GET    /api/quality/analyses/{analysisId}
POST   /api/quality/analyses/pre-analysis-result
POST   /api/quality/analyses/main-analysis-result
POST   /api/quality/analyses/{analysisId}/approve
POST   /api/quality/analyses/{analysisId}/reject
```

Main result request:

```json
{
  "order_id": "uuid",
  "operation_id": "uuid",
  "plant_visit_id": "uuid",
  "analysis_type_code": "main",
  "result_status_code": "functionally_nok",
  "sample_code": "S-100",
  "analysis_at": "2026-06-01T09:00:00Z",
  "parameters": [
    {
      "quality_parameter_id": "uuid",
      "measured_value": 99.90,
      "unit_code": "%"
    }
  ],
  "decision_note": "Outside specification."
}
```

Approve request:

```json
{
  "reason_code": "SPECIALIST_FUNCTIONAL_APPROVAL",
  "reason_note": "Approved after documented specialist review."
}
```

Rules:

- Only Analysis Specialist can approve functionally NOK main analysis.
- Technical invalid and functionally NOK must be separate.
- Every attempt must be stored.

---

## 11. Documents and printing APIs

```text
GET    /api/documents
GET    /api/documents/{documentId}
POST   /api/orders/{orderId}/documents/generate
POST   /api/documents/{documentId}/print
POST   /api/documents/{documentId}/reprint
POST   /api/documents/{documentId}/mark-handed-over
GET    /api/print-jobs
GET    /api/print-jobs/{printJobId}
```

Reprint request:

```json
{
  "printer_hardware_id": "uuid",
  "reason_code": "PRINTER_FAILURE",
  "reason_note": "Original printer jammed."
}
```

Rules:

- Reprint requires audit.
- Exit blocked until mandatory documents are generated and provided.
- Printer failures create alert/event.

---

## 12. Exit API

### POST `/api/terminal/gate/exit-identify`

Request:

```json
{
  "hardware_object_id": "exit-gate-uuid",
  "identifier": "chip-or-tan-value",
  "identifier_type": "chip_card",
  "trailer_chip_identifier": "optional",
  "trailer_license_plate": "SW-FT-100"
}
```

Response:

```json
{
  "decision": "approved",
  "message": "Exit approved.",
  "plant_visit_id": "uuid",
  "blocking_reasons": [],
  "correlation_id": "uuid"
}
```

Denied example:

```json
{
  "decision": "denied",
  "message": "Exit blocked. Mandatory documents not printed.",
  "blocking_reasons": [
    {
      "code": "MANDATORY_DOCUMENT_NOT_PROVIDED",
      "label": "Mandatory documents are not printed/provided."
    }
  ],
  "correlation_id": "uuid"
}
```

---

## 13. Events, alerts, audit APIs

```text
GET  /api/events
GET  /api/events/{eventId}
GET  /api/audit-logs
GET  /api/alerts
GET  /api/alerts/{alertId}
POST /api/alerts/{alertId}/acknowledge
POST /api/alerts/{alertId}/resolve
```

Acknowledge/resolve requires user, timestamp, and optional/required reason based on severity.

---

## 14. Integration APIs / internal service endpoints

These should be protected with service credentials.

```text
POST /api/integrations/sap/orders/import
POST /api/integrations/device-gateway/events
POST /api/integrations/device-gateway/loading-measurements
POST /api/integrations/printer/callback
POST /api/integrations/cloud-sync/callback
```

For MVP development, use jobs and fake adapters first.
