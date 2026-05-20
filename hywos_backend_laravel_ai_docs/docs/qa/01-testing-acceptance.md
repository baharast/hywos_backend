# 01 — Testing and Acceptance Strategy

## 1. Test levels

| Level | Scope |
|---|---|
| Unit tests | Domain services, enums, status transitions, matching logic, eligibility logic, analysis decision rules. |
| Feature/API tests | Laravel routes, validation, permissions, resources, command workflows. |
| Integration tests | MySQL, queues, fake SAP, fake device gateway, fake printer. |
| End-to-end process tests | Variants A-D from entry to exit. |
| Security tests | Auth, RBAC, forbidden actions, sensitive field hiding, audit logs. |
| Acceptance/UAT | Dispatcher, Operator, Analysis Specialist, Operations Manager workflows. |

---

## 2. Critical backend tests

### 2.1 Auth/RBAC

- User can login/logout.
- Locked user cannot login.
- Unauthorized user cannot block driver.
- Dispatcher cannot approve functionally NOK main analysis.
- Operator cannot override quality decision.
- Analysis Specialist can approve quality decision with reason.
- Role/permission changes create audit logs.

### 2.2 Driver management

- Create driver with required fields.
- Driver code unique.
- Driver list filters work.
- Driver detail does not expose `national_id_hash`.
- Block/unblock requires reason.
- Block/unblock creates audit and event logs.
- Blocked driver cannot pass entry gate.

### 2.3 Order and matching

- Valid SAP order imports.
- Incomplete SAP order enters clarification.
- Ambiguous order matching creates clarification and returns conflict.
- No auto-guessing.
- Assignment validates blocked/expired driver/trailer.
- Assignment writes history and audit.

### 2.4 Plant visit

- Entry gate approved creates Plant Visit.
- Denied entry logs event.
- Terminal login continues Visit.
- Tractor plate is recorded.
- Trailer/no-trailer state is recorded.
- Correction request creates clarification.
- Exit closes Visit only when eligible.

### 2.5 Variants

Variant A:

- Parking space assigned.
- Parking confirmation updates trailer and space.
- Wrong trailer opens clarification.

Variant B:

- Panel login validates station/order/trailer/driver.
- Loading release blocked until pre-analysis OK.
- Loading completion updates statuses.

Variant C:

- Loaded trailer pickup requires quality checked.
- Documents required before exit.
- Wrong trailer blocks exit.

Variant D:

- Empty trailer pickup frees parking.
- Process transitions into Variant B.

### 2.6 Analysis

- Pre-analysis has maximum three attempts.
- Third functionally NOK blocks trailer/loading.
- Third technically invalid opens fault/clarification.
- Main analysis timing configurable.
- Main technically invalid allows exactly one repeat.
- Main functionally NOK blocks documents/exit.
- Specialist approval writes audit.

### 2.7 Documents and printing

- Document generation blocked if quality not approved.
- Print job created.
- Print failure creates alert.
- Reprint requires reason.
- Mandatory document not handed over blocks exit.

### 2.8 Integrations

- SAP failure creates connection log and alert.
- Device gateway offline blocks automatic loading release.
- Printer failure blocks exit.
- Reader unknown identifier denies action and logs event.

---

## 3. Acceptance criteria for backend MVP

Backend is acceptable when:

- Core migrations run cleanly.
- Seed roles/permissions/statuses exist.
- Auth/RBAC works.
- Driver, trailer, tractor, company, auth media APIs exist.
- Order import stub exists.
- Order assignment and status history exist.
- Plant Visit entry/terminal registration exists.
- Variants A-D are represented by backend workflow statuses and services.
- Loading release service enforces prerequisites.
- Analysis decision service enforces rules.
- Document/print lifecycle exists.
- Exit eligibility service returns precise blocking reasons.
- Audit and event logging are implemented for critical actions.
- Tests cover critical happy paths and denied paths.
- Swagger/OpenAPI or equivalent API documentation is available.
- All ASP.NET references are removed from backend implementation plans.

---

## 4. KPI/reporting test cases

The backend should support queries for:

- Loaded quantity per day/week/month/year.
- Loaded quantity by quality.
- Loading count by filling station.
- Failed analyses.
- Entry-to-exit residence time.
- Registration-to-loading throughput time.
- Clarification case counts and resolution time.
- Alarm frequency.
- Interface uptime/failure.
- Printer failure/reprint counts.
