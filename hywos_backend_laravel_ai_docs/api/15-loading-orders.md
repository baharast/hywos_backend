# Loading Orders API

Foundation for the **Loading Orders** module (FillTrack Loading Orders UX
Spec V2.2). A loading order is the commercial intent — customer + product +
target quantity — and tracks the dispatcher's progress turning it into a
fulfillable visit (driver + trailer + readiness checks).

> Read [`00-conventions.md`](00-conventions.md) first.

---

## 0. Status of this module

| Layer | State |
|---|---|
| Migration (`loading_orders` table) | ✅ implemented |
| Enums (`LoadingOrderStatus`, `LoadingOrderSource`, `AssignmentState`, `DriverTask`) | ✅ implemented |
| `LoadingOrder` model + `LoadingOrderReadinessService` (status derivation) | ✅ implemented |
| `SAP_OWNED_FIELDS` constant + SAP-field protection pattern | ✅ implemented (controller enforces 423 `SAP_FIELDS_LOCKED` via `App\Services\Sap\SapFieldGuard`) |
| Seeder — 5 demo orders covering DRAFT / NEEDS_ASSIGNMENT / READY / IN_PROGRESS / BLOCKED | ✅ implemented |
| `LOADING_ORDER_*` audit constants | ✅ implemented (11 of them; emitted by the controller) |
| **REST endpoints** (CRUD, assign-driver, assign-trailer, unassign-*, block, unblock, cancel, events-audit) | ✅ **implemented** — see §3 / §4 / §6 / §7 |
| Auto-provisioning side effects on create (bay line + gate-entry TAN) via `OrderProvisioningService` | ✅ implemented — see §5.4 |
| `bayLine` / `tan` / `fillingTan` exposed on the resource | ✅ implemented — see §4.2 / §8 |
| `assigned_bay_line_id` / `_code` / `_name` columns on `loading_orders` (migration `2026_05_30_140000_add_bay_line_to_loading_orders`) | ✅ implemented |
| SAP import endpoint | ⏳ not implemented yet (see SAP Sync spec for the inbound shape) |

This document describes the data model + resource shape + status derivation
rules so the frontend can build screens and stub mock responses now. The
endpoint section is a **forward contract** — actual paths and bodies will be
locked in when the controller lands. Treat URLs in §3+ as a target, not a
promise.

---

## 1. Status derivation — the single most important rule

`status` is **derived**, not freely settable. Per V2.2 §4.4, every save runs
through `App\Services\LoadingOrders\LoadingOrderReadinessService::deriveStatus()`
which evaluates this resolution order (first match wins):

| # | Condition | Result |
|---|---|---|
| 1 | `cancelled_at` is set | `cancelled` |
| 2 | `blocked_at` is set (and not unblocked) | `blocked` |
| 3 | `active_plant_visit_id` OR `active_loading_operation_id` is set | `in_progress` |
| 4 | Previous persisted `status` was `completed` AND no active operation | `completed` |
| 5 | Required order data missing (`customer_id`, `product_quality`, `target_quantity`, `unit`) | `draft` |
| 6 | Driver required but not assigned | `needs_assignment` |
| 7 | Trailer required but not assigned | `needs_assignment` |
| 8 | All required assignments complete | `ready` |

The persisted `loading_orders.status` column is a **cache** of this derivation
— never the source of truth. The frontend should also treat it as a cached
display value: it will be correct as of the most recent save, but a server-side
change to assignment / blocking / cancellation always re-derives it.

### 1.1 Controller-settable anchors

Controllers do NOT set `status` directly. They mutate the underlying anchors:

| To reach `status` | Set / clear |
|---|---|
| `blocked` | `blocked_at` (with `blocking_reason` + `blocking_reason_code`) |
| (unblock) | clear `blocked_at` + `blocking_reason` |
| `cancelled` | `cancelled_at` (with `cancellation_reason` + `cancellation_reason_code`) |
| `in_progress` | `active_plant_visit_id` / `active_loading_operation_id` populated by Plant Visit / Loading Operation flows |
| `completed` | service-side transition when an operation cleanly closes |

This is **why** there is no "Release to Driver" action: readiness is
calculated, not toggled.

### 1.2 Status tones

| `status.value` | `label` | `tone` |
|---|---|---|
| `draft` | `Draft` | `neutral` |
| `needs_assignment` | `Needs Assignment` | `warning` |
| `ready` | `Ready` | `success` |
| `in_progress` | `In Progress` | `info` |
| `completed` | `Completed` | `success` |
| `blocked` | `Blocked` | `danger` |
| `cancelled` | `Cancelled` | `offline` |

---

## 2. Assignment states — driver and trailer are independent

Per V2.2 §4.2 / §4.3, driver assignment and trailer assignment are SEPARATE,
distinct states. They must NOT be merged into one vague "assigned" status.

| state | when | tone |
|---|---|---|
| `assigned` | the matching `assigned_*_id` is set | `success` |
| `not_assigned` | id missing AND task_flow requires it | `warning` |
| `not_required` | task_flow does not require this party | `neutral` |

### 2.1 Which task_flow requires what

```
task_flow                        requiresDriver  requiresTrailer
─────────────────────────────────────────────────────────────────
trailer_filling                       yes              yes
park_trailer                          yes              yes
pickup_loaded_trailer                 yes              no
pickup_empty_trailer_then_load        yes              no
documents_exit                        yes              no
exit_only                             no               no
```

When `task_flow` is `null` we default to "driver required, trailer required"
(safer for parking/loading tasks).

`task_flow` itself uses the canonical 6-task set from APV V1.6 — the Loading
Orders V2.2 spec uses a slightly different label set; see
[`16-plant-visits.md`](16-plant-visits.md) §4 for the label mapping.

---

## 3. Endpoints (forward contract)

> ✅ **Implemented.** Paths/bodies below match the live controller. Treat as
> the canonical reference for request/response shapes.

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/loading-orders` | List with summary + filters |
| GET | `/api/loading-orders/{id}` | Get one |
| POST | `/api/loading-orders` | Create manual order (SAP orders land via import) |
| PUT | `/api/loading-orders/{id}` | Update non-SAP-owned fields |
| POST | `/api/loading-orders/{id}/assign-driver` | Set driver |
| POST | `/api/loading-orders/{id}/unassign-driver` | Clear driver |
| POST | `/api/loading-orders/{id}/assign-trailer` | Set trailer |
| POST | `/api/loading-orders/{id}/unassign-trailer` | Clear trailer |
| POST | `/api/loading-orders/{id}/block` | Block with required reason |
| POST | `/api/loading-orders/{id}/unblock` | Clear block (reason optional) |
| POST | `/api/loading-orders/{id}/cancel` | Cancel with required reason |
| GET | `/api/loading-orders/{id}/events-audit` | Per-order audit + event timeline |

**Not in scope** — DELETE (orders are audit-bearing; cancel is the soft-end
state), bulk endpoints, export.

---

## 4. List — `GET /api/loading-orders` (forward contract)

### 4.1 Query parameters

| param | type | values |
|---|---|---|
| `search` | string | `LIKE %term%` against `order_no`, `sap_reference`, `customer_name`, `assigned_driver_name`, `assigned_trailer_label` |
| `status` | string | one of the §1.2 values |
| `source` | string | `sap` or `manual` |
| `customer_id` | uuid | filter by commercial customer |
| `carrier_id` | uuid | filter by freight forwarder |
| `assigned_driver_id` | uuid | filter by driver |
| `assigned_trailer_id` | uuid | filter by trailer |
| `task_flow` | string | one of the DriverTask values |
| `planned_from` | ISO 8601 | planned window start ≥ this |
| `planned_to` | ISO 8601 | planned window end ≤ this |
| `is_sap_owned` | bool | filter by SAP-protected flag |
| `attention` | bool | shortcut: `status in (needs_assignment, blocked)` |
| `sort` | string | one of `order_no`, `created_at`, `updated_at`, `planned_window_start`, `status` (prefix `-` for desc) |
| `per_page` | int | default 25 |
| `page` | int | 1-based |

### 4.2 Example response

```json
{
  "message": "Loading orders retrieved",
  "data": [
    {
      "id": "uuid",
      "orderNo": "LO-2026-0003",
      "source": { "value": "sap", "label": "SAP" },
      "sapReference": "SAP-450012999",
      "externalReference": null,

      "customer":  { "id": "uuid", "name": "ACME Industrial GmbH" },
      "carrier":   { "id": "uuid", "name": "Schenker DE" },

      "productQuality": "Hydrogen 5.0",
      "targetQuantity": "500.000",
      "unit": "kg",

      "plannedWindow": {
        "start": "2026-05-25T11:00:00+00:00",
        "end":   "2026-05-25T14:00:00+00:00"
      },

      "driver": {
        "id": "uuid",
        "name": "Müller, Hans",
        "code": "DRV-1001",
        "assignmentState": { "value": "assigned", "label": "Assigned", "tone": "success" }
      },
      "trailer": {
        "id": "uuid",
        "label": "TRL-001",
        "plate": "SW-TR-100",
        "assignmentState": { "value": "assigned", "label": "Assigned", "tone": "success" }
      },

      "bayLine": {
        "id":   "uuid",
        "code": "BL-01",
        "name": "Bay Line 01"
      },

      "tan": {
        "id": "uuid",
        "reference": "TAN-2026-0042",
        "display":   "••5837",
        "status":    "active",
        "purpose":   { "value": "gate_entry", "label": "Gate entry", "tone": "info" },
        "usageState":{ "value": "unused",     "label": "Unused",      "tone": "info" },
        "isSingleUse": true,
        "expiresAt":   null,
        "issuedAt":    "2026-05-25T09:30:00+00:00"
      },

      "fillingTan": null,

      "taskFlow":     { "value": "trailer_filling", "label": "Trailer Filling" },
      "currentStep":  null,

      "documents": {
        "requiresCertificate":  true,
        "requiresDeliveryNote": true,
        "requiresQmDocument":   false
      },

      "status": { "value": "ready", "label": "Ready", "tone": "success" },

      "blocking": null,
      "cancellation": null,

      "isLockedByExecution": false,
      "activePlantVisit": null,
      "activeLoadingOperationId": null,

      "isSapOwned": true,
      "lockedFields": [
        "order_no", "sap_reference", "customer_id", "carrier_id",
        "product_quality", "target_quantity", "unit"
      ],

      "notes": null,
      "createdAt": "2026-05-25T09:00:00+00:00",
      "updatedAt": "2026-05-25T09:30:00+00:00"
    }
  ],
  "summary": {
    "total": 5,
    "draft": 1,
    "needsAssignment": 1,
    "ready": 1,
    "inProgress": 1,
    "blocked": 1,
    "cancelled": 0,
    "completed": 0
  },
  "meta": { "current_page": 1, "per_page": 25, "total": 5, "last_page": 1, "first_visible_row": 1, "last_visible_row": 5 },
  "last_updated_at": "2026-05-25T09:30:00+00:00",
  "correlation_id": "..."
}
```

When `status = blocked`, `blocking` becomes:

```json
"blocking": {
  "reason":     "Customer credit review pending",
  "reasonCode": "CREDIT_HOLD",
  "blockedAt":  "2026-05-25T08:45:00+00:00",
  "blockedBy":  null
}
```

When `status = cancelled`, `cancellation` becomes:

```json
"cancellation": {
  "reason":     "Customer cancelled order",
  "reasonCode": "CUSTOMER_REQUEST",
  "cancelledAt": "2026-05-25T09:00:00+00:00",
  "cancelledBy": null
}
```

When `status = in_progress`, `activePlantVisit` becomes:

```json
"activePlantVisit": { "id": "uuid", "visitNo": "PV-2026-0019" }
```

---

## 5. Create — `POST /api/loading-orders` (forward contract)

### 5.1 Request body (manual order)

```json
{
  "customer_id": "uuid",
  "carrier_id":  "uuid",
  "product_quality": "Hydrogen 5.0",
  "target_quantity": 300.000,
  "unit": "kg",
  "planned_window_start": "2026-05-26T09:00:00+00:00",
  "planned_window_end":   "2026-05-26T13:00:00+00:00",
  "task_flow": "trailer_filling",
  "requires_certificate":  true,
  "requires_delivery_note": true,
  "requires_qm_document":   false,
  "external_reference": "PO-12345",
  "notes": "Customer requested priority slot."
}
```

### 5.2 Validation rules

| field | required | rule |
|---|---|---|
| `customer_id` | **yes** | char(36); rejected if customer is `blocked` or `is_active=false` |
| `carrier_id` | no | char(36) |
| `product_quality` | **yes** | string, max 100 |
| `target_quantity` | **yes** | decimal(12,3), > 0 |
| `unit` | no | string in (`kg`, `t`, `nm3`), default `kg` |
| `planned_window_start` / `planned_window_end` | no | ISO 8601; end must be ≥ start when both provided |
| `task_flow` | no | one of the DriverTask values |
| `requires_certificate` / `requires_delivery_note` / `requires_qm_document` | no | boolean |
| `assigned_driver_id` | no | char(36) — driver must be active + not blocked |
| `assigned_trailer_id` | no | char(36) — trailer must be active + chip ok |
| `external_reference` | no | string, max 100 |
| `notes` | no | text, max 5000 |

**Forbidden on create from the API**:

- `order_no` — server generates `LO-YYYY-NNNN`.
- `sap_reference`, `is_sap_owned` — only SAP import sets these.
- `status`, `current_step`, `blocked_at`, `cancelled_at`, `active_*` — derived
  or set by lifecycle endpoints.

### 5.3 Success (201)

Returns the full Loading Order resource. `status` will be `draft`,
`needs_assignment`, or `ready` depending on what was provided.

### 5.4 Auto-provisioning side effects

The controller fires two best-effort side effects **inside the same DB
transaction** as the order create. Both are wired through
`App\Services\LoadingOrders\OrderProvisioningService`:

#### Bay line

- Picks the first `is_active = true` + `status_code = 'free'` bayline
  whose `allowed_product` matches the order's `product_quality` (or any
  active free line when `product_quality` is unset).
- Stable ordering by `code` for deterministic demo behaviour.
- Bay-line `status_code` stays `free` — actual reservation belongs to
  Loading Control (V3.2 §8). This is just the planned bay for the
  management list.
- On success the order row gets `assigned_bay_line_id`,
  `assigned_bay_line_code`, `assigned_bay_line_name` populated and the
  resource returns the `bayLine` object documented in §4.2.
- On failure (no candidate / DB error) → warning logged, `bayLine: null`
  on the response. The 201 is still returned.

#### TAN (gate-entry)

- Only fires when `assigned_driver_id` was set at create time. Without
  a driver, `tan: null`.
- Issues a single-use TAN bound to the driver AND the order
  (`auth_media.order_id = order.id`, `tan_purpose = 'gate_entry'`,
  `is_single_use = true`).
- `expires_at = null` (open-ended); the dispatcher revokes manually if
  the order is cancelled or reassigned.
- Reference format: `TAN-YYYY-NNNN`. See
  [14-tans.md §1.1](14-tans.md#11-tan_purpose-and-the-two-issuance-paths).
- **Idempotent + driver-rotating**: re-running provisioning on the same
  order with the same driver returns the existing TAN. If the driver
  changes (via `POST /{id}/assign-driver`), the previous gate-entry TAN
  is `BLOCKED` with reason `Order reassigned to a different driver`
  and a new one minted.

`fillingTan` is **always null** at create time. It is provisioned later
when the driver confirms a `task=filling` order at the kiosk — see
[23-driver-workflow.md §5](23-driver-workflow.md#5-confirm-order--post-apiterminalsessionidorder).

---

## 6. Update — `PUT /api/loading-orders/{id}` (forward contract)

### 6.1 SAP-owned field protection

When `is_sap_owned = true`, the following fields are **locked from local
edits** (mirrors the Customer / Carrier SAP-protection pattern):

```
order_no, sap_reference, customer_id, carrier_id,
product_quality, target_quantity, unit
```

Attempting to PUT any of these fields on an SAP-owned order returns `423`:

```json
{
  "message": "Cannot update SAP-owned fields locally. Use a controlled correction request.",
  "code": "SAP_FIELDS_LOCKED",
  "details": { "lockedFields": ["target_quantity", "customer_id"] },
  "correlation_id": "..."
}
```

The resource always exposes `lockedFields` as a hint so the UI can disable
those inputs proactively without waiting for the 423.

### 6.2 Locked-by-execution

Once `is_locked_by_execution = true` (active plant visit exists), additional
fields are frozen: `assigned_driver_id`, `assigned_trailer_id`, `task_flow`,
`planned_window_*`. The controller will return:

```json
{ "code": "ORDER_LOCKED_BY_EXECUTION", "message": "...", "details": { "lockedFields": [...] } }
```

`isLockedByExecution` is exposed on the resource.

### 6.3 Always-writable

- `notes`
- `external_reference`
- `requires_qm_document` (the cert/delivery flags are spec-driven and lock with SAP)

---

## 7. Lifecycle action endpoints (forward contract)

All actions follow the §7 "Critical action contract" in
[`00-conventions.md`](00-conventions.md#7-critical-action-contract):
`POST`, required `reason`, optional `reason_code`, audit + event written
before success.

### 7.1 Assign driver / trailer

```http
POST /api/loading-orders/{id}/assign-driver
{ "driver_id": "uuid" }

POST /api/loading-orders/{id}/assign-trailer
{ "trailer_id": "uuid" }
```

- No `reason` required (it is not a critical action — it is dispatcher work).
- Both write an audit row (`loading_order.driver_assigned`,
  `loading_order.trailer_assigned`) so the order's history shows when
  assignment happened and what the previous value was.
- Re-assigning replaces the previous value and emits one audit row covering
  the swap.
- Rejects with `409 DRIVER_BLOCKED` / `TRAILER_BLOCKED` /
  `TRAILER_CHIP_MISSING` when the target is not in a usable state.
- Rejects with `423 ORDER_LOCKED_BY_EXECUTION` when an active visit exists.
- **`assign-driver` also rotates the gate-entry TAN**: any previous
  active TAN with `tan_purpose='gate_entry'` for this order is
  `BLOCKED` with reason `Order reassigned to a different driver`, then
  a fresh TAN is issued for the new driver. The filling TAN
  (`tan_purpose='filling'`) is NOT touched here — it rotates only at
  terminal confirm time. See [§5.4](#54-auto-provisioning-side-effects).

### 7.2 Unassign driver / trailer

```http
POST /api/loading-orders/{id}/unassign-driver
{ "reason": "Driver reassigned to a priority order" }

POST /api/loading-orders/{id}/unassign-trailer
{ "reason": "Trailer pulled for inspection" }
```

`reason` is **required** (≥ 3, ≤ 1000 chars). Resource returns to
`needs_assignment` if no replacement exists.

### 7.3 Block / Unblock

```http
POST /api/loading-orders/{id}/block
{ "reason": "Customer credit review pending", "reason_code": "CREDIT_HOLD" }

POST /api/loading-orders/{id}/unblock
{ "reason": "Credit confirmed; resume." }
```

- `block` requires `reason` (≥ 3); `reason_code` optional max 100.
- Block sets `blocked_at = now()` + persists reason / code.
- Already-blocked returns `409 ALREADY_BLOCKED`.
- Unblock clears `blocked_at` + reason fields; not-blocked returns
  `409 NOT_BLOCKED`. Audit row written either way.

### 7.4 Cancel

```http
POST /api/loading-orders/{id}/cancel
{ "reason": "Customer cancelled order", "reason_code": "CUSTOMER_REQUEST" }
```

- Sticky once set — `cancelled_at` is monotone, never cleared.
- Rejects with `409 ALREADY_CANCELLED` on re-submission.
- Rejects with `423 ORDER_LOCKED_BY_EXECUTION` while an active operation exists
  (cancel only on `draft` / `needs_assignment` / `ready` / `blocked`).

---

## 8. Resource shape — full field reference

Field path uses dotted notation for nested objects.

| field | type | source | notes |
|---|---|---|---|
| `id` | uuid | `id` | route key |
| `orderNo` | string | `order_no` | unique, server-generated |
| `source.value` / `.label` | enum | `source` | `sap` or `manual` |
| `sapReference` | string \| null | `sap_reference` | unique when set |
| `externalReference` | string \| null | `external_reference` | free-text PO number etc. |
| `customer.id` / `.name` | uuid / string | `customer_id`, `customer_name` | name is denormalized |
| `carrier.id` / `.name` | uuid / string \| null | `carrier_id`, `carrier_name` | denormalized |
| `productQuality` | string | `product_quality` | |
| `targetQuantity` | decimal string | `target_quantity` | precision 12,3; serialised as string to avoid precision loss |
| `unit` | string | `unit` | `kg` / `t` / `nm3` |
| `plannedWindow.start` / `.end` | ISO 8601 \| null | `planned_window_start`, `planned_window_end` | |
| `driver.id` / `.name` / `.code` | uuid / string / string \| null | `assigned_driver_*` | all denormalized for list speed |
| `driver.assignmentState` | object | derived | `{value, label, tone}` (§2) |
| `trailer.id` / `.label` / `.plate` | uuid / string / string \| null | `assigned_trailer_*` | denormalized |
| `trailer.assignmentState` | object | derived | `{value, label, tone}` (§2) |
| `bayLine.id` / `.code` / `.name` | uuid / string / string \| null | `assigned_bay_line_*` | denormalized; auto-picked at create-time (§5.4). Null when no candidate matched. |
| `tan` | object \| null | composite (see [14-tans.md §1.1](14-tans.md#11-tan_purpose-and-the-two-issuance-paths)) | Gate-entry TAN (`tan_purpose='gate_entry'`). Null until a driver is assigned. `expires_at` is `null` (open-ended). Raw TAN value is never returned. |
| `fillingTan` | object \| null | composite (see [14-tans.md §1.1](14-tans.md#11-tan_purpose-and-the-two-issuance-paths)) | Filling-station TAN (`tan_purpose='filling'`, `FT-YYYY-NNNN`). Null until the driver confirms a `task=filling` order at the kiosk — see [23-driver-workflow.md §5](23-driver-workflow.md#5-confirm-order--post-apiterminalsessionidorder). |
| `taskFlow.value` / `.label` | enum | `task_flow` | DriverTask |
| `currentStep` | string \| null | `current_step` | echoes the plant_visit step when in progress |
| `documents.requiresCertificate` / `.requiresDeliveryNote` / `.requiresQmDocument` | booleans | `requires_*` | drives the documents readiness panel |
| `status` | object | derived (see §1) | `{value, label, tone}` |
| `blocking` | object \| null | `blocked_at` + `blocking_reason*` | shape in §4.2 |
| `cancellation` | object \| null | `cancelled_at` + `cancellation_reason*` | shape in §4.2 |
| `isLockedByExecution` | boolean | `is_locked_by_execution` | drives `ORDER_LOCKED_BY_EXECUTION` 423 |
| `activePlantVisit.id` / `.visitNo` | uuid / string \| null | `active_plant_visit_id`, `active_plant_visit_no` | denormalized — populated by Plant Visit flow |
| `activeLoadingOperationId` | uuid \| null | `active_loading_operation_id` | |
| `isSapOwned` | boolean | `is_sap_owned` | true when source = SAP and ownership retained |
| `lockedFields` | string[] | derived from `SAP_OWNED_FIELDS` | only present when `isSapOwned = true` — see §6.1 |
| `notes` | string \| null | `notes` | |
| `createdAt`, `updatedAt` | ISO 8601 | timestamps | second precision |

> **Tractor / machine plate is NOT on this resource.** Per V2.2 §11.1 the
> plate is captured at the Driver Terminal during the visit, not on the
> order. It lives on the plant_visit row.

---

## 9. Error codes

| code | http | when |
|---|---|---|
| `LOADING_ORDER_NOT_FOUND` | 404 | id does not exist |
| `SAP_FIELDS_LOCKED` | 423 | PUT touches a SAP-owned field on an SAP-owned order; `details.lockedFields` lists them |
| `ORDER_LOCKED_BY_EXECUTION` | 423 | PUT or lifecycle action while an active visit / operation is bound |
| `ALREADY_BLOCKED` / `NOT_BLOCKED` | 409 | block/unblock idempotency violation |
| `ALREADY_CANCELLED` | 409 | cancel re-submission |
| `DRIVER_BLOCKED` | 409 | assign-driver targets a blocked driver |
| `TRAILER_BLOCKED` | 409 | assign-trailer targets a blocked trailer |
| `TRAILER_CHIP_MISSING` | 409 | assign-trailer targets a trailer without a usable chip |
| `CUSTOMER_BLOCKED` | 409 | create targets a blocked customer |
| (validation envelope) | 422 | Laravel default — required field missing, enum mismatch, decimal out of range |

---

## 10. Frontend notes

- **Never set `status` directly.** Use the lifecycle action endpoints
  (block / unblock / cancel) and the assignment endpoints. The status badge
  on every response is the authoritative current value.
- **Always render driver and trailer as TWO separate badges.** Use
  `driver.assignmentState` and `trailer.assignmentState`. Do not collapse
  them.
- **Disable SAP-owned inputs proactively** using `lockedFields`. Show a small
  lock icon + tooltip "Managed by SAP". This avoids a round-trip 423 on every
  attempted edit.
- **Disable execution-locked inputs proactively** using `isLockedByExecution`.
  Once a plant visit exists, driver/trailer/task can no longer change.
- **`activePlantVisit` deeplinks to the plant visits screen** when set
  ([`16-plant-visits.md`](16-plant-visits.md)).
- **Show `blocking.reason` and `cancellation.reason` prominently** in the
  status header — the user needs to see *why* without digging into history.
- **Order numbers are display-stable.** They never change after creation;
  use them as the user-facing identifier in URLs / search.
- **`source = sap`** orders should also show the SAP reference next to the
  order number. Filter and search must work against it.
- **`tan` block** — show the reference (`TAN-2026-NNNN`) + masked value
  (`••XXXX`) inline in the order row. Add an "expires" chip only when
  `expiresAt` is non-null; auto-issued TANs are open-ended (null). The
  raw 6-digit value is never returned by this endpoint — it was shown
  exactly once on `POST /api/tans` (manual flow) or never (auto flow,
  driver picks it up via the kiosk).
- **`fillingTan` block** — null until the driver confirms a `task=filling`
  order at the kiosk. When populated, render with a distinct visual
  treatment (different colour / `purpose.tone = success`) so the
  dispatcher can tell entry vs filling at a glance. Reference prefix is
  `FT-YYYY-NNNN`.
- **`bayLine` block** — shows the planned bay. Stays `free` on the
  bayline side until Loading Control actually reserves it; this is
  intentional — `bayLine` here is the planned slot, not the reservation.

---

## 11. SAP-owned field protection — for reference

```php
LoadingOrder::SAP_OWNED_FIELDS = [
  'order_no',
  'sap_reference',
  'customer_id',
  'carrier_id',
  'product_quality',
  'target_quantity',
  'unit',
];
```

When the order is `is_sap_owned = true`, these are read-only at the
controller. The model exposes them on the resource so the UI can warn the
user before submitting.

---

## 12. Audit constants (reserved for the controller)

| constant | event name |
|---|---|
| `LOADING_ORDER_CREATED` | `loading_order.created` |
| `LOADING_ORDER_UPDATED` | `loading_order.updated` |
| `LOADING_ORDER_DRIVER_ASSIGNED` | `loading_order.driver_assigned` |
| `LOADING_ORDER_DRIVER_UNASSIGNED` | `loading_order.driver_unassigned` |
| `LOADING_ORDER_TRAILER_ASSIGNED` | `loading_order.trailer_assigned` |
| `LOADING_ORDER_TRAILER_UNASSIGNED` | `loading_order.trailer_unassigned` |
| `LOADING_ORDER_BLOCKED` | `loading_order.blocked` |
| `LOADING_ORDER_UNBLOCKED` | `loading_order.unblocked` |
| `LOADING_ORDER_CANCELLED` | `loading_order.cancelled` |
| `LOADING_ORDER_SAP_FIELD_UPDATE_REJECTED` | `loading_order.sap_field_update_rejected` |
| `LOADING_ORDER_SAP_IMPORTED` | `loading_order.sap_imported` |

---

## 13. Database schema

```sql
CREATE TABLE loading_orders (
  id                            CHAR(36) PRIMARY KEY,

  order_no                      VARCHAR(50)  NOT NULL UNIQUE,
  source                        VARCHAR(20)  NOT NULL DEFAULT 'manual',  -- sap | manual
  sap_reference                 VARCHAR(100) NULL UNIQUE,
  external_reference            VARCHAR(100) NULL,

  customer_id                   CHAR(36) NULL,
  customer_name                 VARCHAR(255) NULL,
  carrier_id                    CHAR(36) NULL,
  carrier_name                  VARCHAR(255) NULL,

  product_quality               VARCHAR(100) NULL,
  target_quantity               DECIMAL(12,3) NULL,
  unit                          VARCHAR(10)  NOT NULL DEFAULT 'kg',

  planned_window_start          TIMESTAMP NULL,
  planned_window_end            TIMESTAMP NULL,

  assigned_driver_id            CHAR(36) NULL,
  assigned_driver_name          VARCHAR(255) NULL,
  assigned_driver_code          VARCHAR(50)  NULL,
  assigned_trailer_id           CHAR(36) NULL,
  assigned_trailer_label        VARCHAR(100) NULL,
  assigned_trailer_plate        VARCHAR(50)  NULL,

  -- Added by 2026_05_30_140000_add_bay_line_to_loading_orders.php
  assigned_bay_line_id          CHAR(36)     NULL,   -- soft FK to baylines.id
  assigned_bay_line_code        VARCHAR(50)  NULL,   -- denormalized
  assigned_bay_line_name        VARCHAR(100) NULL,   -- denormalized

  task_flow                     VARCHAR(40)  NULL,  -- DriverTask enum

  requires_certificate          TINYINT(1) NOT NULL DEFAULT 1,
  requires_delivery_note        TINYINT(1) NOT NULL DEFAULT 1,
  requires_qm_document          TINYINT(1) NOT NULL DEFAULT 0,

  status                        VARCHAR(30)  NOT NULL DEFAULT 'draft',  -- cached derived value
  current_step                  VARCHAR(80)  NULL,
  blocking_reason               TEXT NULL,
  blocking_reason_code          VARCHAR(100) NULL,
  blocked_at                    TIMESTAMP NULL,
  blocked_by_user_id            BIGINT UNSIGNED NULL,
  cancellation_reason           TEXT NULL,
  cancellation_reason_code      VARCHAR(100) NULL,
  cancelled_at                  TIMESTAMP NULL,
  cancelled_by_user_id          BIGINT UNSIGNED NULL,
  is_locked_by_execution        TINYINT(1) NOT NULL DEFAULT 0,

  active_plant_visit_id         CHAR(36) NULL,   -- soft FK
  active_plant_visit_no         VARCHAR(50) NULL,
  active_loading_operation_id   CHAR(36) NULL,   -- soft FK

  is_sap_owned                  TINYINT(1) NOT NULL DEFAULT 0,

  notes                         TEXT NULL,

  created_by_user_id            BIGINT UNSIGNED NULL,
  updated_by_user_id            BIGINT UNSIGNED NULL,
  created_at                    TIMESTAMP NULL,
  updated_at                    TIMESTAMP NULL,

  INDEX (source),
  INDEX (customer_id),
  INDEX (carrier_id),
  INDEX (product_quality),
  INDEX (assigned_driver_id),
  INDEX (assigned_trailer_id),
  INDEX (task_flow),
  INDEX (status),
  INDEX (active_plant_visit_id),
  INDEX (active_loading_operation_id),
  INDEX (is_sap_owned),
  INDEX (status, source),
  INDEX (status, updated_at)
);
```

---

## 14. Seeded demo data

`LoadingOrderSeeder` creates 5 orders covering the spectrum:

| `order_no` | `source` | `is_sap_owned` | derived `status` | notes |
|---|---|---|---|---|
| `LO-2026-0001` | manual | no | `draft` | `target_quantity` null forces draft |
| `LO-2026-0002` | sap | yes | `needs_assignment` | full data, no driver / trailer |
| `LO-2026-0003` | sap | yes | `ready` | full data + driver + trailer |
| `LO-2026-0004` | sap | yes | `in_progress` | linked to `PV-2026-0019` (set by Plant Visit seeder) |
| `LO-2026-0005` | manual | no | `blocked` | `blocking_reason_code = CREDIT_HOLD` |

`LO-2026-0003` and `LO-2026-0004` are also referenced by the Parking and
Plant Visits modules — they are the primary fixtures for cross-module demo
flows.

---

## 15. cURL cheatsheet (forward contract)

```bash
# List with summary
curl -s "http://localhost/api/loading-orders?per_page=10" | jq .

# Filter: orders needing attention
curl -s "http://localhost/api/loading-orders?attention=true" | jq .

# Create a manual order
curl -s -X POST "http://localhost/api/loading-orders" \
     -H "Content-Type: application/json" \
     -d '{
       "customer_id":"<uuid>","product_quality":"Hydrogen 5.0",
       "target_quantity":300,"unit":"kg","task_flow":"trailer_filling"
     }' | jq .

# Assign driver
curl -s -X POST "http://localhost/api/loading-orders/<uuid>/assign-driver" \
     -H "Content-Type: application/json" \
     -d '{"driver_id":"<uuid>"}' | jq .

# Block with reason
curl -s -X POST "http://localhost/api/loading-orders/<uuid>/block" \
     -H "Content-Type: application/json" \
     -d '{"reason":"Customer credit review pending","reason_code":"CREDIT_HOLD"}' | jq .

# Cancel
curl -s -X POST "http://localhost/api/loading-orders/<uuid>/cancel" \
     -H "Content-Type: application/json" \
     -d '{"reason":"Customer cancelled order","reason_code":"CUSTOMER_REQUEST"}' | jq .
```

---

## 16. Change log

- **v1 (2026-05-25)** — initial doc. Foundation (migration / model / readiness
  service / enums / seeder / audit constants) is live; endpoints are forward
  contract and will land when the controller is built.
- **v1.1 (2026-05-27)** — controller landed. All 11 REST endpoints + the
  per-order `events-audit` timeline are now implemented. Resource shape and
  error codes match this document. SAP import endpoint remains pending.
