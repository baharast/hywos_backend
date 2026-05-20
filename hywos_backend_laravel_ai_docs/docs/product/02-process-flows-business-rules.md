# 02 — Process Flows and Business Rules

## 1. Process backbone

The official MVP process has:

- Five driver terminal actions.
- Four dispatching variants.
- Strict matching and blocking rules.
- Auditable manual clarification when the system cannot safely continue.

---

## 2. Five driver terminal actions

At the driver terminal, the driver sees exactly these actions:

1. `Loading`
2. `Park trailer in the parking area`
3. `Pick up a trailer`
4. `Print delivery note and exit`
5. `Exit`

Backend rule:

- These are UI actions, not all separate business variants.
- The backend must route each action to the correct process variant or support step.
- Do not add extra driver actions in MVP without product approval.

---

## 3. Four official dispatching variants

| Variant | Name | Meaning |
|---|---|---|
| A | Park Trailer in the Parking Area | Driver brings a trailer but should not directly load it. |
| B | Drive to Filling Station with Own Trailer and Load | Driver brought the trailer assigned to the loading order and should load it. |
| C | Pick Up Loaded Trailer from Parking Area and Exit | Driver picks up a loaded trailer and exits with documents. |
| D | Pick Up Empty Trailer and Then Load | Driver picks up an empty trailer and continues into Variant B loading. |

---

## 4. Common entry, login, and registration flow

### 4.1 Entry gate

1. Driver arrives at entry gate.
2. Driver identifies with chip or TAN.
3. Gate/controller sends identification to backend.
4. Backend validates:
   - auth medium exists,
   - auth medium active/not expired/not blocked,
   - driver exists and is active/not blocked,
   - driver has authorization for site/process,
   - order/TAN validity if order-specific.
5. Backend returns entry decision and driver-language message.
6. Denied attempts create event/security log.
7. Approved attempts create or continue a Plant Visit.

### 4.2 Driver terminal login

1. Driver logs in again with chip or TAN.
2. Backend creates/continues Plant Visit.
3. Backend asks/records whether trailer is present.
4. If trailer chip exists in project, trailer chip is scanned/entered.
5. If trailer chip is used, trailer plate is also queried.
6. Backend records tractor-unit license plate.
7. If action requires order matching, backend identifies order by chip/TAN/plates/assignments.
8. Backend shows confirmation data.
9. Driver confirms or requests correction.
10. If correction requested, create clarification case and stop normal flow.

### 4.3 Confirmation data

Backend should provide:

- Driver identity and eligibility.
- ADR/training/validity attention if available.
- Trailer identity, status, type, capacity.
- Tractor license plate.
- Order number, SAP order number, customer, product/quality, target quantity, destination, time window.
- Current instruction.
- Blocking/warning flags.

### 4.4 No auto-guessing rule

When the backend finds multiple possible orders, trailers, operations, or assignments:

- Do not select one automatically.
- Create clarification case or return `409 conflict`.
- Provide enough candidates/context for an authorized dispatcher/operator to decide.
- Log the ambiguity event.

---

## 5. Variant A — Park Trailer in Parking Area

### Goal

Driver brings a trailer and receives a specific parking space. After parking, the system decides the follow-up instruction.

### Entry conditions

- Active order/instruction with action `park`.
- Driver and trailer identified or registered.
- Free parking space available.
- No blocking driver/trailer/order status.

### Backend steps

1. Validate Plant Visit and assignment.
2. Reserve/assign parking space.
3. Return parking instruction to terminal.
4. Driver physically parks trailer.
5. Confirm parking via reader, terminal, or operator.
6. Update trailer location/status.
7. Update parking space status.
8. Update Plant Visit status to `trailer_parked`.
9. Evaluate next instruction after driver logs in again.

### Exit conditions

- Trailer physically and systemically assigned to parking space.
- Trailer status is parked.
- Parking space is occupied.
- Plant Visit has follow-up instruction or clarification.

### Blocking rules

- Assigned parking space no longer free → stop and require dispatcher/operator reassignment.
- Wrong trailer detected → clarification.
- Device fault → manual confirmation requires reason and audit.
- No follow-up instruction → waiting/clarification.

---

## 6. Variant B — Own Trailer Loading

### Goal

Driver drives to assigned filling station with the trailer brought and loads it.

### Entry conditions

- Driver/trailer/order uniquely assigned.
- Filling station available/reserved.
- Driver/trailer authorized.
- Pre-analysis is OK or required before release.

### Backend steps

1. Identify order and operation.
2. Validate driver/trailer/tractor/order/station assignment.
3. Return filling station instruction.
4. At filling station panel, driver logs in with chip/TAN and trailer chip if configured.
5. Backend validates panel/station assignment.
6. Backend starts or validates pre-analysis.
7. If pre-analysis OK, release loading.
8. Track loading status and PLC/device data through integration adapter.
9. Trigger or receive main analysis at configured timing.
10. On loading completion, determine document and exit eligibility.

### Exit conditions

- Loading completed.
- Main analysis is running or complete according to scenario.
- If main analysis OK/approved, documents can be generated.
- Driver is instructed to return to terminal for documents/exit.

### Blocking rules

- Wrong station → deny release, log event.
- Wrong trailer/chip → deny release, open clarification.
- Pre-analysis fails three times → reject loading, block trailer, alert operator and analysis specialist.
- PLC/device communication failure → block automatic release.
- Main analysis functionally NOK → block documents/exit unless Analysis Specialist approval.

---

## 7. Variant C — Pick Up Loaded Trailer and Exit

### Goal

Driver picks up a loaded trailer from parking, receives documents, and exits.

### Entry conditions

- Loaded trailer available in parking.
- Main analysis completed positive or approved.
- Associated order is quality checked.
- Mandatory documents can be generated or exist.
- Driver/tractor assigned to pickup.

### Backend steps

1. Driver logs in, usually without trailer.
2. Driver selects `Pick up a trailer`.
3. Backend finds exactly one loaded trailer assigned to driver/order.
4. Return parking location, trailer ID/plate, loaded quantity, quality, document status.
5. Driver couples trailer.
6. Confirm trailer by chip/plate/reader/operator.
7. Record tractor-trailer coupling.
8. Generate/print/provide certificate and delivery note.
9. Set Plant Visit ready for exit.
10. Exit gate checks driver/trailer/order/document status.
11. Close Plant Visit after exit.

### Blocking rules

- Loaded trailer has not passed main analysis → do not allow exit.
- Wrong trailer picked up → block exit and clarify.
- Printing fails → block exit until reprint/replacement workflow succeeds.
- Multiple loaded trailers match → dispatcher/manager must decide.

---

## 8. Variant D — Pick Up Empty Trailer then Load

### Goal

Driver picks up an empty parked trailer and continues into Variant B.

### Entry conditions

- Empty trailer available in parking.
- Loading order exists.
- Driver/tractor assigned or uniquely matched.
- Filling station can be assigned/reserved.

### Backend steps

1. Driver logs in without trailer or tractor-only.
2. Driver selects `Pick up a trailer`.
3. Backend determines assigned trailer is empty and should be loaded.
4. Return parking space and trailer identity.
5. Driver couples trailer.
6. Confirm trailer identity.
7. Record Driver + Tractor + Trailer + Order relationship.
8. Free parking space.
9. Update Plant Visit/order status.
10. Route driver to filling station and continue Variant B.

### Blocking rules

- Empty trailer not physically/systemically present → block.
- Trailer unsuitable for product/quality/pressure → clarification.
- Multiple matching trailers → dispatcher/manager selection required.
- Pickup confirmation fails → cannot transition to loading.

---

## 9. Print documents and exit support flow

This is not a separate dispatching variant. It is a support step after loading/pickup when operational work is complete.

### Backend steps

1. Driver logs in with chip/TAN.
2. Backend identifies active Plant Visit and order.
3. Backend checks:
   - loading complete,
   - quality state allows documents,
   - mandatory document types known,
   - no blocking clarification,
   - printer available or replacement workflow.
4. Generate missing documents.
5. Queue print jobs.
6. Mark printed/provided/handed over.
7. Instruct driver to exit gate.
8. Exit gate checks final eligibility.

### Blocking rules

- No quality approval → block.
- Missing mandatory document → block.
- Print failed → block.
- Open clarification case → block.
- Wrong trailer at exit → block.

---

## 10. Controlled exit without further operation

Used for cancellation, missing order, wrong assignment, no valid operation, or operator decision.

Rules:

- Must identify active Plant Visit.
- Must record whether trailer leaves with driver or remains on site.
- If abnormal/cancelled, require authorized confirmation and reason.
- Exit gate decision must still validate unresolved blockers.
- Close/update Plant Visit with correct exit reason.

---

## 11. Analysis rules

### 11.1 Pre-analysis

| Rule | System behavior |
|---|---|
| Prerequisites fulfilled | Start pre-analysis. |
| Pre-analysis OK | Release loading. |
| Not OK and attempt count < 3 | Repeat; log event/reason. |
| 3rd functionally NOK | Reject loading; block trailer; alert responsible roles. |
| 3rd technically invalid | Open fault case; manual check required; do not release. |

### 11.2 Main analysis

| Rule | System behavior |
|---|---|
| Loading completed or configured percentage reached | Start main analysis. |
| Main analysis OK | Quality checked; allow document generation. |
| Technically invalid and no technical repeat used | Allow exactly one technical repeat. |
| Technically invalid again | Block completion; escalate. |
| Functionally NOK | No functional repetition; block documents/exit; Analysis Specialist decision required. |

### 11.3 Authority boundary

- Operator may handle technical faults and physical plant safety.
- Operator may not override functional product-quality decisions.
- Analysis Specialist may approve/block quality decisions.
- Functional approval of functionally NOK main analysis requires reason, user, timestamp, measured values, and audit record.

---

## 12. Exception matrix

| Exception | Backend rule | Responsible role |
|---|---|---|
| Invalid/blocked chip/TAN | Deny entry/login; event log. | Driver, Operator |
| Unknown trailer chip | Block assignment/loading; clarification. | Dispatcher, Operator |
| No active order | Do not send to filling station or print documents. | Dispatcher |
| Driver/trailer/order mismatch | Stop process; clarification. | Dispatcher, Operator |
| Multiple matches | Return conflict; no auto-choice. | Dispatcher/Manager |
| SAP unavailable | No new/changed orders; active local orders may continue. | IT/Support, Dispatcher |
| PLC/device gateway unavailable | Block automatic release/gate decisions. | Operator, IT/Support |
| Printer failure | Block exit if mandatory documents not provided. | Operator |
| Gate fault | Do not silently continue; manual opening requires reason. | Operator |
| Terminal/panel unavailable | Self-service blocked; operator/manual route. | Operator |
| Main analysis functionally NOK | Block documents/exit; Analysis Specialist decision. | Analysis Specialist |
