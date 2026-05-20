# 00 — Project AI Context

## 1. Project identity

**Project names used in documents:** HYWOS, FillTrack  
**Domain:** Industrial hydrogen trailer loading management  
**Primary site context:** Tyczka Schweinfurt hydrogen loading site  
**Backend stack for this implementation:** Laravel + MySQL  
**Frontend stack:** Next.js / React / TypeScript  
**Core deployment principle:** On-premise first. The local plant server is the leading system for operational and safety-relevant workflows.

Some source documents mention ASP.NET Core. Treat this as outdated for this implementation. The backend must be Laravel.

---

## 2. What the system does

FillTrack/HYWOS digitalizes and controls the operational process around hydrogen loading:

1. SAP sends loading orders.
2. Dispatcher prepares or verifies driver/trailer/tractor/order assignments.
3. Driver identifies at entry gate.
4. Driver logs in again at the driver terminal.
5. Driver registers tractor plate and trailer information.
6. System matches the driver, trailer, tractor, and order.
7. Driver receives an instruction:
   - park trailer,
   - load own trailer,
   - pick up loaded trailer and exit,
   - pick up empty trailer then load,
   - print documents and exit,
   - controlled exit.
8. Loading occurs at the filling station/panel after authorization and pre-analysis.
9. Main analysis decides product quality.
10. Certificates, delivery notes, and required documents are generated/printed.
11. Exit gate opens only if all blocking conditions are resolved.
12. All critical steps are logged in event/audit history.

---

## 3. Main actors

| Actor | Backend meaning |
|---|---|
| Driver | External/internal driver using gate, terminal, and filling station panel. Does not use the management dashboard in MVP. |
| Admin | Configures users, roles, plant structure, devices, master data, chips, and system settings. |
| Dispatcher / Manager | Assigns orders to drivers, trailers, tractors, stations, slots, and parking spaces. Handles ambiguous assignments. |
| Operator | Monitors operations, gates, terminals, devices, loading stations, alarms, and clarification cases. May perform audited operational actions. |
| Analysis Specialist | Owns product-quality decisions. Can approve/reject blocked quality cases. |
| Operations Manager | Reviews KPIs, throughput, dwell time, station utilization, failed analyses, and audit-relevant reports. |
| IT / Support | Monitors SAP, OPC UA/device gateway, cloud sync, printers, terminals, readers, and support escalation. |
| Auditor | Reviews event journal, audit trail, document generation, manual overrides, and approval records. |

---

## 4. MVP scope

Included:

- Driver management.
- Trailer management.
- Tractor/vehicle management.
- Company, customer, freight forwarder master data.
- Chip card and TAN identification.
- SAP order import/status feedback abstraction.
- Gate entry/exit control abstraction.
- Driver terminal workflows.
- Filling station panel workflows.
- Trailer parking and pickup.
- Loading control.
- Pre-analysis and main-analysis logic.
- Certificate/delivery note/document lifecycle.
- Print/reprint workflows.
- Event journal, audit trail, alarms.
- Device/hardware registry and health monitoring.
- Reporting/KPI foundation.
- RBAC and security logs.

Not included in MVP unless explicitly approved:

- Driver mobile app interaction.
- QR/NFC with private smartphones.
- Carrier telematics integration.
- Commercial invoicing/procurement/accounting.
- Advanced import/export beyond agreed MVP.
- Full maintenance workflows of the hydrogen plant itself.
- Bulk actions in dashboard pages.
- Automatic license plate recognition.
- Silent auto-correction of ambiguous data.

---

## 5. Core source-of-truth corrections

### 5.1 Backend stack

Use Laravel + MySQL. Do not generate ASP.NET architecture, C# code, EF Core entities, or .NET project structures.

### 5.2 Industrial device integrations

Laravel owns the business rules and API. For low-level industrial integrations such as OPC UA, gate controller protocol, card readers, trailer-chip readers, and printers, implement adapter interfaces and integration clients.

Because direct OPC UA support in PHP may be limited depending on the final hardware stack, keep the physical device layer behind an adapter. A separate device gateway service can be introduced later if required. The Laravel backend should not depend on controllers or UI directly knowing PLC node IDs.

### 5.3 Database

Use MySQL as the persistent database. The ERD contains many UUID-style `char(36)` primary keys and some `bigint` history/event identifiers. Use Laravel migrations that preserve this intent.

### 5.4 Frontend contract

The Next.js frontend needs clean, predictable API contracts. Do not expose raw table names or sensitive database fields directly. Use API Resources and DTOs.

---

## 6. Core domain objects

| Object | Meaning |
|---|---|
| Loading Order | Leading operational/commercial object received from SAP or manually handled in defined cases. |
| Order Operation | Execution attempt or operational instance of an order. |
| Plant Visit | Complete site stay from entry to exit. Operational glue linking driver, tractor, trailer, order, gate, terminal, loading, documents, and exit. |
| Driver | Person identified by chip/TAN and allowed/blocked based on status, training, license, and assignments. |
| Tractor | Motorized vehicle unit/tractor plate used in the visit. |
| Trailer | Physical loading carrier, may be parked, empty, loaded, blocked, or assigned. |
| Authentication Medium | Chip card, TAN, trailer chip, operator card, badge, or related ID medium. |
| Hardware Object | Gate, terminal, panel, reader, printer, PLC endpoint, or other device. |
| Quality Analysis | Pre-analysis/main-analysis records and measured values. |
| Certificate / Document | Audit-proof output required for transport and quality evidence. |
| Event Log | Operational facts. |
| Audit Log | Sensitive changes and manual overrides. |
| Alert | Operational/technical issue requiring attention. |
| Clarification Case | Workflow for ambiguous, blocked, or unsafe process situations. |

---

## 7. Golden backend rules

1. Treat operational workflows as state machines.
2. Never update statuses directly from controllers.
3. Use transactions and row locking where two actors/devices might update the same process.
4. Store snapshots for execution/document context.
5. Separate event log from audit log:
   - Event log = what happened operationally.
   - Audit log = who changed what and why.
6. Every blocking action must explain why it is blocked.
7. Every sensitive action must require a reason.
8. Every external integration call should be logged.
9. The backend must support offline/local operation for locally synchronized active orders if SAP/cloud is unavailable.
10. Do not generate code that assumes perfect hardware availability.
