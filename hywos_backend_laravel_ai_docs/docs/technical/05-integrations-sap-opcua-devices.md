# 05 — Integrations: SAP, OPC UA / Device Gateway, Gates, Printers, Readers, Cloud

## 1. Integration philosophy

Laravel is the business backend and source of workflow truth. External integrations must be isolated behind adapters.

Do not hardcode SAP protocol details, PLC node IDs, printer commands, or reader protocols in controllers or UI-facing services.

Use:

- interface classes,
- integration jobs,
- integration message/outbox table,
- connection logs,
- alerts,
- fake adapters for development,
- real adapters after stakeholder/hardware clarification.

---

## 2. SAP integration

### 2.1 Purpose

SAP provides commercial/loading order data and receives process feedback.

Inbound from SAP:

- New loading orders.
- Updated loading orders.
- Customer/source/destination references.
- Product/substance/quality.
- Requested quantity.
- Planned load date/time window.
- Possibly driver/trailer/forwarder assignments, depending on final SAP scope.

Outbound to SAP:

- Registered/started/completed statuses.
- Actual loaded quantity.
- Quality status.
- Analysis result summary if required.
- Document/certificate/delivery note references.
- Timestamps.
- Exception/cancellation statuses.

### 2.2 Protocol open question

The exact SAP protocol is not finalized in source material. Possible options include:

- RFC
- IDoc
- OData
- SOAP
- file-based exchange
- custom REST proxy

Do not implement a protocol-specific connector until confirmed.

### 2.3 Laravel abstraction

Create:

```php
interface SapConnectorInterface
{
    public function importOrders(): SapImportResult;
    public function sendOrderStatus(OrderStatusMessageDto $message): SapSendResult;
}
```

Initial adapters:

- `FakeSapConnector`
- `HttpSapConnector` placeholder
- `SapFileImportConnector` placeholder if needed later

### 2.4 SAP order import flow

1. Scheduled job starts.
2. Connector receives raw orders.
3. Save raw payload to integration message/log.
4. Validate mandatory fields.
5. Upsert `order_header`.
6. Create/update `order_status_history`.
7. Mark complete orders as imported/ready for dispatcher.
8. Mark incomplete orders as clarification needed.
9. Log errors and create alerts.

### 2.5 SAP downtime rule

If SAP is unavailable:

- Do not create new orders from guesswork.
- Continue only locally synchronized active orders.
- Log connection failure.
- Create alert for IT/Support.
- Show operational impact to dispatcher.

---

## 3. OPC UA / PLC and device gateway

### 3.1 Purpose

PLC/device integration supports:

- Filling station status.
- Loading release signals.
- Loading progress.
- Pressure/flow/quantity data.
- Fault codes.
- Gate/device feedback.
- Analysis device status depending on hardware.

### 3.2 Important backend note for Laravel

The original functional concept referenced a .NET OPC UA client. This Laravel implementation should not assume PHP directly controls OPC UA.

Recommended design:

```text
Laravel Backend
  ↕ REST/WebSocket/Queue
Device Gateway Service
  ↕ OPC UA / manufacturer protocols
PLCs / Gate Controllers / Readers / Printers
```

The device gateway can later be implemented in the best technology for hardware integration. Laravel should remain the business/workflow authority.

### 3.3 Device gateway abstraction

```php
interface DeviceGatewayClientInterface
{
    public function getHardwareStatus(string $hardwareObjectId): HardwareStatusDto;
    public function requestLoadingRelease(LoadingReleaseCommandDto $command): DeviceCommandResult;
    public function requestGateOpen(GateOpenCommandDto $command): DeviceCommandResult;
}
```

### 3.4 Device event endpoint

Protected endpoint:

```text
POST /api/integrations/device-gateway/events
```

Payload examples:

- station online/offline,
- loading started,
- loading completed,
- measurement update,
- gate feedback,
- reader scan,
- fault code.

Rules:

- Validate service credential.
- Save raw payload in integration message/log.
- Map to domain event/service.
- Create alert if critical.
- Never let raw device events bypass business rules for release/exit decisions.

---

## 4. Gate controllers

### 4.1 Entry gate

The backend decides if entry is allowed. Gate controller receives a decision.

Decision depends on:

- auth medium validity,
- driver status,
- order/TAN validity if required,
- site authorization,
- blocking conditions.

### 4.2 Exit gate

Exit is stricter than entry.

Decision depends on:

- active Plant Visit,
- correct driver,
- correct trailer if applicable,
- loading status,
- quality status,
- mandatory documents generated/provided,
- no unresolved blocking clarification,
- no manual hold.

### 4.3 Manual gate opening

Manual gate opening requires:

- authorized operator,
- reason,
- event log,
- audit log,
- hardware context,
- Plant Visit context if known.

---

## 5. Printers and document printing

### 5.1 Purpose

Documents may include:

- certificate,
- delivery note,
- QM document,
- ADR/transport document if required.

### 5.2 Print lifecycle

Use `document` + `print_job`.

Statuses:

- `pending`
- `generated`
- `print_queued`
- `printing`
- `printed`
- `print_failed`
- `reprinted`
- `handed_over`

### 5.3 Printer failure rule

If mandatory document printing/provision fails:

- Exit is blocked.
- Event log created.
- Alert created.
- Operator can reprint or choose replacement printer with reason.
- Reprint history must be audit-proof.

---

## 6. Card readers and trailer-chip readers

### 6.1 Reader scan

Reader scan should produce:

- hardware object ID,
- identifier value/hash,
- identifier type,
- timestamp,
- optional signal quality/raw payload.

### 6.2 Matching

The backend maps identifier to `auth_medium`.

Rules:

- Unknown identifier → event + denial or clarification.
- Blocked/expired medium → denial.
- Trailer chip is conditional; if used, trailer plate also queried.
- If no trailer present, driver must be able to state that.

---

## 7. Cloud sync / mobile support

The cloud is non-critical for core MVP operation.

Rules:

- Core operations continue locally if cloud unavailable.
- Cloud sync is outbound or controlled.
- Sync only approved data.
- Cloud/mobile may show stale data with clear notice.
- Store sync state and failures.

---

## 8. Connection logs and alerts

Every integration should produce connection logs:

- source
- destination
- protocol
- status
- message
- timestamp
- correlation ID

Critical failures should create alerts:

- SAP unavailable.
- PLC/device gateway unavailable.
- Gate communication failure.
- Printer failure.
- Card reader offline.
- Cloud sync delayed.
- Analysis device fault.

---

## 9. Fake adapters for development

Before hardware/SAP access is available, implement:

- `FakeSapConnector`
- `FakeDeviceGatewayClient`
- `FakePrinterClient`
- `FakeGateControllerClient`
- `FakeCardReaderClient`

Fake adapters must still call the same domain actions as real adapters.
