<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# MVP Scope and Domain

## MVP goal

The FillTrack MVP digitalizes the critical hydrogen loading workflow around driver identification, trailer/order assignment, plant visits, loading control, analysis/quality, document generation, gate exit and traceability.

## Included MVP backend capabilities

### Operations

- Active Plant Visits.
- Loading Control.
- Gate & Terminal monitoring data.
- Trailer Pool / Car Park state.
- Clarification Cases.

### Orders

- Loading Orders.
- SAP order import status.
- Assignment and process state support.

### Analysis & Quality

- Active analysis records.
- Results and quality decisions.
- Product/quality specification references where needed.
- Analysis devices and status links where available.

### Documents & Reports

- Certificates and delivery notes.
- Print/reprint status and print logs.
- Operational report data where in MVP.

### Alarms & Events

- Active alarms.
- Event journal.
- Change Log / Audit Trail.

### Master Data

- Drivers.
- Trailers.
- Tractor units / vehicles.
- Customers.
- Freight forwarders / carriers.
- Chip Cards.
- TANs.

### System & Devices

- Hardware devices.
- Interface Health.

### Administration

- Users.
- Roles & Permissions.
- Company & Plant Configuration.

## Not included unless explicitly added later

- Commercial invoicing.
- Carrier telematics.
- Full ERP customer master data lifecycle.
- Driver mobile app interactions for MVP.
- QR/NFC with driver private phones.
- Bulk edit/delete/approve flows.
- Full visual plant map editor.
- Low-level PLC address-space editing from dashboard.
- Direct physical control from ordinary dashboard pages without safety-approved scope.

## Key backend domain principles

### Plant Visit as operational glue

A Plant Visit connects the driver, tractor, trailer, order, gate events, terminal sessions, parking actions, loading operation, documents and exit state.

### Order is leading commercial/process object

Loading order context comes from SAP or controlled local/manual creation where allowed. The backend must preserve SAP reference, customer, product/quality and target quantity.

### No auto-guessing

When a driver, trailer, tractor, order, station or document relation is ambiguous, the backend must not guess. It must block the action and create/route a clarification case.

### Traceability

Every important transition must be reconstructable through events/audit logs.

### Local operational reliability

The backend must be designed for an industrial environment. Integration outages must produce safe degraded states rather than hidden failures.
