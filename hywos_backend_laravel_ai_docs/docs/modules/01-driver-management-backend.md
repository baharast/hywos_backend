# Module 01 — Driver Management Backend Specification

## 1. Purpose

Driver Management stores and maintains driver master data and operational eligibility. It supports gate/terminal/loading workflows by answering:

- Is this driver known?
- Is this driver active?
- Is this driver blocked?
- Is training/validity acceptable?
- Does the driver have a valid chip or TAN?
- Which company is the driver related to?
- What is the driver's recent/active plant visit context?

Driver Management is master data. Live visit state belongs to Operations / Active Plant Visits.

---

## 2. Routes

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

No bulk actions.

---

## 3. Driver list API

### 3.1 Query parameters

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
- `created_from`
- `created_to`
- `sort`
- `page`
- `per_page`

### 3.2 Search fields

Search should cover:

- first name
- last name
- driver code
- license number
- phone
- email
- employer company display name
- operator company display name
- masked/display auth medium identifier if available

### 3.3 Default list resource

```json
{
  "id": "uuid",
  "driverCode": "DRV-1001",
  "fullName": "Max Mustermann",
  "initials": "MM",
  "status": "active",
  "trainingStatus": "valid",
  "licenseExpiryDate": "2027-12-31",
  "preferredCulture": "de-DE",
  "identificationStatus": "chip_assigned",
  "employerCompanyName": "Carrier GmbH",
  "operatorCompanyName": "Tyczka",
  "phone": "+49...",
  "email": "max@example.com",
  "lastVisitAt": "2026-05-01T10:00:00Z",
  "activeVisitId": null,
  "hasNotes": true,
  "updatedAt": "2026-05-20T08:00:00Z"
}
```

---

## 4. Driver detail API

Detail resource should include:

- all list fields,
- first name,
- last name,
- national ID last 4,
- license number,
- notes,
- created/updated metadata,
- auth media summary,
- recent plant visits,
- recent audit/events.

Do not include:

- `national_id_hash`
- password/security values
- raw full sensitive identifiers

---

## 5. Create driver

### 5.1 Required fields

- `driver_code` unless backend auto-generates it
- `first_name`
- `last_name`
- `preferred_culture_code`
- `training_status_code` or default
- `is_active`

### 5.2 Optional fields

- `national_id_last4`
- `license_no`
- `license_expiry_date`
- `phone`
- `email`
- `employer_company_id`
- `operator_company_id`
- `notes`

### 5.3 Rules

- Driver can be created without chip.
- Default language should use plant/site default, usually German, if not provided.
- Email validation if present.
- License date must be valid if present.
- Driver code must be unique.
- Creation should create audit log if required by policy and event log for master-data creation.

---

## 6. Edit driver

Rules:

- Load current driver.
- Validate fields.
- Do not expose/edit `national_id_hash`.
- Do not allow direct silent operational block changes unless the dedicated block/unblock endpoint is used.
- If editing key identity fields, audit before/after values.
- Current changes must not retroactively alter historical visit/order/certificate snapshots.

---

## 7. Block driver

### 7.1 Endpoint

```text
POST /api/drivers/{driverId}/block
```

Request:

```json
{
  "reason_code": "TRAINING_EXPIRED",
  "reason_note": "Driver training expired and must be renewed."
}
```

### 7.2 Rules

- Requires permission `drivers.block`.
- Reason required.
- If already blocked, return idempotent result or validation error depending on project decision.
- Set block status.
- Store block reason and timestamp.
- Create audit log.
- Create event log.
- Active gate/loading operations should evaluate blocker immediately.

### 7.3 Effects

A blocked driver:

- cannot be admitted at entry gate,
- cannot continue terminal workflow unless controlled exit/exception allows,
- cannot be assigned to new loading operations,
- should show Danger status in frontend.

---

## 8. Unblock driver

### 8.1 Endpoint

```text
POST /api/drivers/{driverId}/unblock
```

Request:

```json
{
  "reason_code": "TRAINING_RENEWED",
  "reason_note": "Training certificate verified."
}
```

### 8.2 Rules

- Requires permission `drivers.block` or `drivers.unblock`.
- Reason required.
- Unblocking only removes block status.
- Other validity rules still apply: inactive, expired license, missing training, or missing auth medium may still prevent operations.
- Audit and event log required.

---

## 9. Identification summary

A driver's identification status is derived from active auth media:

| Status | Logic |
|---|---|
| `chip_assigned` | Active non-expired chip linked to driver. |
| `tan_available` | Active valid TAN exists and no active chip or TAN is explicitly selected. |
| `missing` | No active valid medium. |
| `blocked` | All relevant media are blocked or driver medium is blocked. |
| `expired` | Relevant medium exists but expired. |

Do not store derived `identificationStatus` unless performance requires it.

---

## 10. Driver eligibility service

Create `DriverEligibilityService`.

Suggested methods:

```php
public function evaluateForGateEntry(Driver $driver, ?OrderHeader $order = null): EligibilityResult;
public function evaluateForTerminalLogin(Driver $driver, PlantVisit $visit): EligibilityResult;
public function evaluateForLoadingRelease(Driver $driver, OrderOperation $operation): EligibilityResult;
public function evaluateForAssignment(Driver $driver): EligibilityResult;
```

Eligibility result should include:

- `allowed: bool`
- `blockingReasons: array`
- `warnings: array`
- `messageCode`
- `messageText`
- `messageCulture`

Blocking reasons:

- driver inactive,
- driver blocked,
- license expired,
- training expired/missing if required,
- auth medium invalid,
- order assignment mismatch,
- no active order,
- security lock.

---

## 11. Related plant visits

Endpoint:

```text
GET /api/drivers/{driverId}/plant-visits
```

Return:

- active visit if exists,
- recent visits,
- related order number,
- tractor/trailer labels,
- statuses,
- clarification indicator.

---

## 12. Events and audit

Endpoint:

```text
GET /api/drivers/{driverId}/events
GET /api/drivers/{driverId}/audit
```

Filter event/audit records by:

- `driver_id`
- or `entity_name=driver` and entity ID
- plus related plant visits and operations when appropriate.

---

## 13. Tests

Required tests:

- Driver list search by name/code/license/company.
- Driver create validation.
- Driver update hides/security-only fields.
- Block requires permission.
- Block requires reason.
- Block writes audit/event.
- Blocked driver denied at gate.
- Unblock requires reason.
- Unblocked driver still blocked by expired license if rule enabled.
- Driver detail does not expose `national_id_hash`.
- No bulk endpoints exist.
