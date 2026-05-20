# 01 — MVP Scope and Domain Model

## 1. Product definition

FillTrack / HYWOS is an industrial management software system for hydrogen trailer loading operations.

The MVP supports the core process from order receipt through driver entry, registration, dispatching, trailer parking/pickup, loading, analysis, document printing, and exit.

The backend must support safe, auditable, on-premise operation. Integrations with SAP, devices, and cloud are important, but the local system remains the operational source of truth for active workflows.

---

## 2. MVP functional scope

### 2.1 Operational scope

The backend must support:

- Entry gate identification.
- Driver terminal login and registration.
- Driver/trailer/tractor/order matching.
- Dispatching and assignment.
- Trailer parking.
- Trailer pickup.
- Filling station authorization and loading.
- Pre-analysis and main-analysis decision rules.
- Document generation and print status.
- Exit eligibility and exit gate decision.
- Operational clarification cases.
- Manual override rules.
- Event and audit logging.

### 2.2 Master-data scope

The backend must support:

- Users, roles, permissions.
- Sites, plant areas, gates, stations, panels, terminals, parking spaces.
- Companies, customers, freight forwarders/carriers.
- Drivers.
- Tractors.
- Trailers.
- Chip cards, trailer chips, TANs, and authentication media.
- Substances/products and quality specifications.
- Hardware objects and integration endpoints.
- Printers and document templates.

### 2.3 Reporting and monitoring scope

The backend must support data foundations for:

- Loaded quantity per period.
- Loaded quantity by quality.
- Number of loadings per station.
- Failed analyses.
- Dwell/residence time.
- Throughput time.
- Clarification cases and resolution time.
- Alarm frequency.
- Interface availability.
- Printer failures and reprints.
- SAP sync failures.

---

## 3. Explicitly not in MVP

Do not implement these unless stakeholders move them into MVP:

- Driver mobile app.
- Private-phone QR/NFC workflows.
- Automatic license plate recognition.
- Carrier telematics.
- Invoicing/procurement/accounting.
- Plant maintenance/repair workflow.
- Bulk dashboard actions.
- Complex approval workflow for driver creation.
- Advanced compliance document management beyond required loading/quality/transport documents.
- Silent data correction or automatic guessing.

---

## 4. Domain object descriptions

### 4.1 Loading Order

The loading order is the leading object. It connects SAP/commercial data to physical execution.

Typical information:

- Internal order ID.
- Order number and SAP order number.
- Customer/source/destination companies.
- Freight forwarder/carrier.
- Product/substance/quality.
- Requested quantity.
- Planned load date/time window.
- Status.
- Assigned driver/trailer/tractor.
- Allowed filling station or pressure range.
- Required documents.
- SAP sync state.
- Correlation ID.

Rules:

- No loading starts without an active loading order.
- Incomplete SAP orders do not enter operation.
- If SAP is down, only already synchronized active orders may continue.
- Completion requires loading, quality decision, documents, and exit rules.

### 4.2 Order Operation

An order operation represents an executable operational instance/attempt of a loading order.

Use it when:

- An order may have attempts.
- A dispatching variant is selected.
- A specific site/station/parking instruction is assigned.
- Actual start/end and success/failure are tracked.

### 4.3 Plant Visit

Plant Visit is the umbrella object for everything that happens during a vehicle/driver stay at the site.

It connects:

- Driver.
- Tractor.
- Trailer.
- Order operation.
- Entry/exit gate.
- Terminal actions.
- Visit steps.
- Authentication attempts.
- Loading.
- Analysis.
- Documents.
- Clarification cases.
- Events and audit context.

Every operational step should be linked to a Plant Visit if it occurs during a site visit.

### 4.4 Driver

A driver has master-data and operational eligibility:

- Identity and driver code.
- License number and expiry.
- Language preference.
- Employer/operator company.
- Active/inactive status.
- Block status and reason.
- Training status.
- Chip/TAN identification.
- Visit/order history.

Rules:

- Blocked drivers cannot be admitted at the gate or assigned to new operation steps.
- Expired or invalid driver eligibility should block or warn according to configured rule.
- Driver does not use management dashboard in MVP; they use gate, terminal, and panel.

### 4.5 Tractor

The tractor/vehicle plate is important for traceability but may not always be pre-known.

Rules:

- Tractor-unit license plate must always be recorded or confirmed during terminal registration.
- If plate changes during or after visit, keep historical snapshots unchanged.
- Current master data changes must not retroactively modify certificates/delivery notes.

### 4.6 Trailer

Trailer is the physical loading carrier.

It may have:

- Trailer chip.
- License plate.
- Serial number.
- Type/capacity/pressure/product suitability.
- Owner/operator company.
- Inspection/TÜV/validity.
- Parking location.
- Empty/loaded/blocked/ready status.
- Assignment to order/driver/tractor/plant visit.

Rules:

- Wrong trailer blocks loading and exit.
- If trailer chip exists, license plate is also queried.
- The system must also support “no trailer present”.
- Trailer status/location changes must be event/audit logged.

### 4.7 Authentication Medium

Authentication media include:

- Driver chip.
- Trailer chip.
- TAN.
- Operator card/badge.
- Hardware identification.

Rules:

- Chip is primary.
- TAN is fallback.
- TAN should have validity period and optional one-time-use behavior.
- Invalid/expired/blocked media deny access/release and create events.
- Identifier values should be masked or hashed where appropriate.

### 4.8 Quality Analysis

Analysis protects product quality and determines document/exit eligibility.

There are two major types:

- Pre-analysis before loading release.
- Main analysis during or after loading.

Result categories:

- `ok`
- `functionally_nok`
- `technically_invalid`

Rules:

- Pre-analysis can repeat up to three times.
- Main analysis technical invalid can have exactly one technical repeat.
- Main analysis functionally NOK does not allow functional repetition.
- Analysis Specialist is required for documented functional approval of a functionally NOK main analysis.

### 4.9 Documents

Documents include:

- Certificate.
- Delivery note.
- QM document.
- ADR / transport-related output if required by final scope.

Document lifecycle must track:

- Generated.
- Printed.
- Reprinted.
- Failed.
- Handed over.
- Archived.

Exit is blocked until mandatory document requirements are satisfied.

### 4.10 Events, alerts, audit

Use three separate concepts:

| Concept | Purpose |
|---|---|
| Event Log | Operational facts: entry, login, matching, status change, loading release, document print, exit denial. |
| Alert | Active or historical issue requiring attention: SAP failure, printer fault, analysis fault, gate fault. |
| Audit Log | Sensitive changes: role/permission, master data, manual override, status override, block/unblock, quality approval. |

---

## 5. Core statuses to model

Use explicit enums or constrained values.

### Order status

Suggested values:

- `created`
- `imported`
- `incomplete`
- `scheduled`
- `assigned`
- `ready_for_registration`
- `registered`
- `in_loading`
- `loading_completed`
- `quality_check_open`
- `quality_checked`
- `quality_blocked`
- `documents_ready`
- `ready_for_exit`
- `completed`
- `cancelled`
- `blocked`
- `clarification_needed`

### Operation status

- `planned`
- `scheduled`
- `assigned`
- `registered`
- `trailer_park_instruction`
- `trailer_parked`
- `pickup_instruction`
- `assigned_to_bay`
- `in_pre_analysis`
- `released_for_loading`
- `in_loading`
- `loading_completed`
- `in_main_analysis`
- `waiting_for_documents`
- `ready_for_exit`
- `closed`
- `rejected`
- `cancelled`
- `clarification_needed`

### Plant visit status

- `created`
- `entry_requested`
- `entered`
- `registered`
- `waiting_for_instruction`
- `trailer_parking`
- `trailer_parked`
- `trailer_pickup`
- `trailer_picked_up`
- `assigned_to_bay`
- `loading`
- `loading_completed`
- `waiting_for_analysis`
- `waiting_for_documents`
- `ready_for_exit`
- `exited`
- `closed`
- `blocked`
- `clarification_needed`
- `cancelled`

### Trailer status

- `unknown`
- `available`
- `assigned`
- `arrived`
- `parked_empty`
- `parked_loaded`
- `coupled`
- `in_loading`
- `loaded`
- `ready_for_pickup`
- `picked_up`
- `exited`
- `blocked`
- `maintenance`
- `clarification_needed`

### Analysis status

- `open`
- `in_progress`
- `ok`
- `functionally_nok`
- `technically_invalid`
- `repeat_required`
- `failed`
- `approved`
- `rejected`
- `quality_blocked`

### Document status

- `not_required`
- `pending`
- `generated`
- `print_queued`
- `printed`
- `reprinted`
- `print_failed`
- `handed_over`
- `archived`
- `blocked`
