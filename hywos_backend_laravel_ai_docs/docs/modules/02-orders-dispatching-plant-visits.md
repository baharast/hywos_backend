# Module 02 — Orders, Dispatching and Plant Visits Backend Specification

## 1. Purpose

Orders and Plant Visits are the operational backbone.

- `order_header` represents the SAP/loading order.
- `order_operation` represents execution/attempt/dispatching context.
- `plant_visit` represents physical site stay from entry to exit.

The backend must connect commercial order data with physical driver/trailer/loading execution.

---

## 2. Order import and queue

### 2.1 SAP import

Orders usually come from SAP. Imported orders must be validated before they enter operations.

Minimum order fields:

- order number,
- SAP order number/reference,
- customer,
- source/destination if available,
- freight forwarder/carrier if available,
- substance/product,
- quality specification,
- requested quantity,
- planned loading date/time window,
- required documents,
- status,
- correlation ID.

### 2.2 Incomplete orders

If mandatory data is missing:

- status becomes `incomplete` or `clarification_needed`,
- order does not become operational,
- event log created,
- dispatcher can see issue,
- no driver may be routed based on incomplete order.

### 2.3 SAP downtime

- Existing locally synchronized active orders may continue.
- New orders must not be improvised locally unless manual order scope is explicitly approved.
- Connection failure creates alert for IT/Support.

---

## 3. Dispatching and assignment

Dispatcher assigns/verifies:

- driver,
- tractor,
- trailer,
- carrier/freight forwarder,
- site,
- filling station,
- parking space,
- process variant,
- time window.

Rules:

- Missing/ambiguous data keeps order in clarification.
- System suggestions must be confirmed if required.
- Assignment changes after visit start require audit and snapshots.
- Driver/trailer/tractor blocked states prevent assignment unless authorized override exists.

---

## 4. Process variant selection

Use values:

- `variant_a_park_trailer`
- `variant_b_own_trailer_loading`
- `variant_c_pickup_loaded_exit`
- `variant_d_pickup_empty_then_load`

Support actions:

- `print_documents_exit`
- `controlled_exit`

Variant selection should live on `order_operation`.

---

## 5. Execution snapshot

When an order operation/plant visit becomes active, preserve snapshot data used later for history and documents.

Suggested `execution_snapshot_json`:

```json
{
  "driver": {
    "driver_id": "uuid",
    "driver_code": "DRV-1001",
    "full_name": "Max Mustermann"
  },
  "tractor": {
    "tractor_id": "uuid",
    "license_plate": "SW-TR-200"
  },
  "trailer": {
    "trailer_id": "uuid",
    "trailer_code": "TRL-100",
    "license_plate": "SW-FT-100"
  },
  "order": {
    "order_id": "uuid",
    "order_no": "O-100",
    "sap_order_no": "SAP-100",
    "customer": "Customer GmbH",
    "product": "Hydrogen 5.0",
    "requested_quantity_kg": 1000
  }
}
```

Rules:

- Later master-data changes do not rewrite snapshots.
- Snapshot correction requires authorized correction, reason, audit, and event.

---

## 6. Plant Visit lifecycle

Suggested lifecycle:

1. `created`
2. `entry_requested`
3. `entered`
4. `registered`
5. `waiting_for_instruction`
6. variant-specific statuses:
   - `trailer_parking`
   - `trailer_parked`
   - `trailer_pickup`
   - `trailer_picked_up`
   - `assigned_to_bay`
   - `loading`
   - `loading_completed`
7. `waiting_for_analysis`
8. `waiting_for_documents`
9. `ready_for_exit`
10. `exited`
11. `closed`

Exception statuses:

- `blocked`
- `clarification_needed`
- `cancelled`

---

## 7. Order matching service

Create `OrderMatchingService`.

Inputs:

- driver,
- auth medium/TAN,
- trailer chip/plate,
- tractor plate,
- selected driver action,
- current site,
- current plant visit.

Outputs:

- unique order/operation match,
- no match,
- multiple matches,
- blocking reason,
- clarification case ID.

Matching sources:

- direct order assignment,
- TAN order link,
- driver assignment,
- trailer assignment,
- license plates,
- time window,
- status,
- site.

Rules:

- If one valid match exists, continue.
- If none, return no active order and likely clarification/waiting.
- If multiple, return conflict and create clarification.
- Never select by newest/closest order silently.

---

## 8. Plant Visit APIs

```text
GET /api/plant-visits
GET /api/plant-visits/{plantVisitId}
GET /api/plant-visits/{plantVisitId}/steps
GET /api/plant-visits/{plantVisitId}/events
POST /api/terminal/gate/entry-identify
POST /api/terminal/driver/login
POST /api/terminal/driver/{plantVisitId}/registration
POST /api/terminal/driver/{plantVisitId}/select-action
POST /api/terminal/driver/{plantVisitId}/confirm-context
POST /api/terminal/gate/exit-identify
```

---

## 9. Clarification cases

Create clarification case when:

- driver data wrong,
- trailer data wrong,
- no active order,
- multiple possible orders/trailers,
- driver/trailer/order mismatch,
- device fault blocks confirmation,
- manual correction required,
- exit requested with unresolved blockers.

Clarification case fields should link to:

- order,
- operation,
- plant visit,
- driver,
- tractor,
- trailer,
- hardware,
- reason,
- assigned role/user,
- blocking flag.

---

## 10. Tests

Required tests:

- SAP import creates valid order.
- Incomplete order becomes clarification/incomplete.
- Dispatcher assignment validates driver/trailer/tractor states.
- Ambiguous order matching returns conflict and creates clarification.
- Plant Visit is created at approved entry.
- Terminal login continues existing visit.
- Registration records tractor plate and trailer/no-trailer state.
- Context confirmation with `confirmed=false` creates clarification.
- Status history created for order/operation changes.
- Snapshot not changed when driver/trailer master data changes later.
