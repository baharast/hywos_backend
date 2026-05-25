# Gate & Terminal Monitor API

Backs the **Operations → Gate & Terminal Monitor** page (FillTrack Gate &
Terminal Monitor UX Spec V2.3). Touchpoint-first read-only dashboard for the
three driver-facing touchpoints: Entry Gate, Control Room / Driver Terminal,
Exit Gate.

> Read [`00-conventions.md`](00-conventions.md) first.

---

## 0. Status of this module

| Layer | State |
|---|---|
| Migration (`terminal_sessions` table) | ✅ implemented |
| Enums (`GateTerminalTouchpoint`, `GateTerminalSessionState`, `GateTerminalCurrentScreen`) | ✅ implemented |
| `TerminalSession` model (UUID PK, soft FKs, `nextSessionNo`) | ✅ implemented |
| Seeder — 8 demo sessions covering every V2.3 §9 state across all 3 touchpoints | ✅ implemented |
| `GATE_TERMINAL_*` audit constants | ✅ reserved (5 of them — emitted by a future write controller, not by this MVP slice) |
| **REST endpoints** (touchpoints / sessions / show) | ✅ implemented |
| Write surface (start session / mark needs-operator / resolve / record fault) | ⏳ not implemented yet (V2.3 §2.2 explicitly excludes unsafe controls from MVP; the read-only slice is the operator triage view) |

---

## 1. Endpoints

> ✅ **Implemented.** All three are read-only and inherit `CorrelationIdMiddleware`.

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/gate-terminal-monitor/touchpoints` | 3-card touchpoint board (one card per touchpoint) |
| GET | `/api/gate-terminal-monitor/sessions` | Paginated session list with filters + summary |
| GET | `/api/gate-terminal-monitor/sessions/{id}` | Single session detail |

**Not in scope** — POST/PUT/PATCH/DELETE, bulk endpoints, manual gate open,
force exit, release driver, PLC/ESD commands, alarm acknowledgement, device
configuration, order assignment, quality decisions. V2.3 §2.2 and §17 are
explicit: this page does not control hardware.

---

## 2. Touchpoint summary — `GET /api/gate-terminal-monitor/touchpoints`

Returns exactly **3** cards, one per `GateTerminalTouchpoint`. The card
order is fixed: `entry_gate`, `driver_terminal`, `exit_gate`.

### 2.1 Card display priority (V2.3 §6.3)

For each touchpoint the controller picks one "primary" session to show using
this resolution order (first match wins):

| # | Condition | What the card shows |
|---|---|---|
| 1 | open `device_fault` row at this touchpoint | the fault — driver may be null |
| 2 | open `denied` or `needs_operator` row | the blocked session + reason |
| 3 | `service_mode` row | maintenance state |
| 4 | `active` row | the current driver session + currentScreen |
| 5 | only `idle` rows (or no rows at all) | calm idle card |

Within the same priority class we tie-break on `last_activity_at desc`.

### 2.2 Example response

```json
{
  "message": "Touchpoint board retrieved",
  "data": [
    {
      "touchpoint": "entry_gate",
      "touchpointLabel": "Entry Gate",
      "state": { "value": "device_fault", "label": "Device Fault", "tone": "danger" },
      "activeSessionCount": 2,
      "primarySessionId": "uuid",
      "driverName": null,
      "visitNo": null,
      "currentScreen": null,
      "issueReason": "Reader/gate feedback unavailable.",
      "actionNeeded": "Open Device Detail.",
      "lastActivityAt": "2026-05-28T09:14:30+00:00"
    },
    {
      "touchpoint": "driver_terminal",
      "touchpointLabel": "Control Room / Driver Terminal",
      "state": { "value": "needs_operator", "label": "Needs Operator", "tone": "orange" },
      "activeSessionCount": 3,
      "primarySessionId": "uuid",
      "driverName": "Tomasz Nowak",
      "visitNo": "PV-2026-0022",
      "currentScreen": { "value": "trailer_identification", "label": "Trailer Identification" },
      "issueReason": "Trailer chip not recognized.",
      "actionNeeded": "Open Clarification.",
      "lastActivityAt": "2026-05-28T09:11:00+00:00"
    },
    {
      "touchpoint": "exit_gate",
      "touchpointLabel": "Exit Gate",
      "state": { "value": "active", "label": "Active", "tone": "info" },
      "activeSessionCount": 1,
      "primarySessionId": "uuid",
      "driverName": "Klaus Becker",
      "visitNo": "PV-2026-0019",
      "currentScreen": { "value": "exit_validation", "label": "Exit Validation" },
      "issueReason": null,
      "actionNeeded": null,
      "lastActivityAt": "2026-05-28T09:16:00+00:00"
    }
  ],
  "last_updated_at": "2026-05-28T09:16:00+00:00",
  "correlation_id": "..."
}
```

When all 3 touchpoints are calm the controller still returns 3 cards — every
one carries `state.value = "idle"` and the per-card driver / visit / screen
fields are null. The list-level zero-summary rule lives on the `/sessions`
endpoint, not here.

---

## 3. Sessions list — `GET /api/gate-terminal-monitor/sessions`

### 3.1 Query parameters (V2.3 §7)

| param | type | values |
|---|---|---|
| `search` | string | LIKE %term% against `driver_name`, `driver_code`, `visit_no`, `order_no`, `trailer_label`, `device_label`, `session_no` |
| `touchpoint` | enum | one of the 3 GateTerminalTouchpoint values |
| `session_state` | enum | one of the 6 GateTerminalSessionState values |
| `current_screen` | enum | one of the 7 GateTerminalCurrentScreen values |
| `driver_id` | uuid | filter by driver |
| `plant_visit_id` | uuid | filter by visit |
| `device_health` | string | `online` / `warning` / `fault` / `offline` / `service_mode` |
| `last_activity_from` | ISO 8601 | inclusive lower bound |
| `last_activity_to` | ISO 8601 | inclusive upper bound |
| `sort` | string | one of `last_activity_at`, `created_at`, `session_state`, `touchpoint` (prefix `-` for desc) — default `-last_activity_at` |
| `per_page` | int | default 25 |
| `page` | int | 1-based |

> **No standalone `needs_operator` filter** — it lives inside `session_state`
> already, per V2.3 §7 + §17. The frontend must not duplicate it as a primary
> filter chip.

### 3.2 Summary block (V2.3 §5 — zero-suppression)

The summary follows a strict zero-suppression rule:

- If every counter is 0 → emit only `{ "activeSessions": 0, "message": "No active gate or terminal issues" }`.
- Otherwise → emit `activeSessions` plus only the non-zero counters from this set:
  - `entryDenied` — sessions at `entry_gate` with `session_state = denied`
  - `exitBlocked` — sessions at `exit_gate` with `session_state IN (denied, needs_operator)`
  - `needsOperator` — sessions where `needs_operator = true` OR `session_state = needs_operator`
  - `deviceFaults` — sessions where `session_state = device_fault`

The FE must never render an `entryDenied: 0` card — backend already drops it.

### 3.3 Example response

```json
{
  "message": "Terminal sessions retrieved",
  "data": [
    {
      "id": "uuid",
      "sessionNo": "TS-2026-0004",
      "touchpoint": { "value": "driver_terminal", "label": "Control Room / Driver Terminal" },
      "device": { "id": null, "label": "Terminal-CR-B", "health": "online" },
      "driver": { "id": "uuid", "name": "Tomasz Nowak", "code": "DRV-1003" },
      "plantVisit": { "id": "uuid", "visitNo": "PV-2026-0022" },
      "order": null,
      "trailer": null,
      "currentScreen": { "value": "trailer_identification", "label": "Trailer Identification" },
      "sessionState": { "value": "needs_operator", "label": "Needs Operator", "tone": "orange" },
      "issueReason": "Trailer chip not recognized.",
      "actionNeeded": "Open Clarification.",
      "needsOperator": true,
      "supportRequested": true,
      "clarificationCaseId": "uuid",
      "lastActivityAt": "2026-05-28T09:11:00+00:00"
    }
  ],
  "summary": { "activeSessions": 6, "entryDenied": 1, "needsOperator": 1, "deviceFaults": 1 },
  "meta": { "current_page": 1, "per_page": 25, "total": 8, "last_page": 1, "first_visible_row": 1, "last_visible_row": 8 },
  "last_updated_at": "2026-05-28T09:16:00+00:00",
  "correlation_id": "..."
}
```

---

## 4. Session detail — `GET /api/gate-terminal-monitor/sessions/{id}`

Same shape as one row from §3 — the Selected Session Details section in the
UI (V2.3 §14) is composed entirely from this single payload. Returns
**404 `TERMINAL_SESSION_NOT_FOUND`** when the id is unknown.

---

## 5. Enums

### 5.1 `GateTerminalTouchpoint` (3 values, V2.3 §15.2)

| value | label |
|---|---|
| `entry_gate` | Entry Gate |
| `driver_terminal` | Control Room / Driver Terminal |
| `exit_gate` | Exit Gate |

Bay Line 1-6 is intentionally absent — those belong to Loading Control
(V2.3 §6.2 + §17).

### 5.2 `GateTerminalSessionState` (6 values, V2.3 §9 + §15.2)

| value | label | tone |
|---|---|---|
| `idle` | Idle | neutral |
| `active` | Active | info |
| `denied` | Denied | danger |
| `needs_operator` | Needs Operator | orange |
| `device_fault` | Device Fault | danger |
| `service_mode` | Service Mode | maintenance |

V2.3 deliberately **removes** `waiting` and `blocked`:
- "waiting" surfaces as `issueReason` + `actionNeeded` on an `active` row
- "blocked" translates to `denied`, `needs_operator`, or `device_fault`
  depending on context

### 5.3 `GateTerminalCurrentScreen` (7 values, V2.3 §10)

| value | label |
|---|---|
| `driver_login` | Driver Login |
| `trailer_identification` | Trailer Identification |
| `tractor_machine_plate` | Tractor / Machine Plate |
| `task_selection` | Task Selection |
| `instruction` | Instruction |
| `documents` | Documents |
| `exit_validation` | Exit Validation |

"Denied" and "Support" are NOT screens — they are session states / issue
reasons.

---

## 6. `needsOperator` source-of-truth rule (V2.3 §11)

`needsOperator` is **backend-set only** — the frontend must never infer it
from waiting states. Allowed sources (all server-side):

1. `needs_operator = true` set explicitly by backend session API.
2. `support_requested = true` — driver pressed the call/help button at the
   terminal. In MVP the seeder uses this for exactly one demo row (TS-2026-0004).
3. Open clarification case linked via `clarification_case_id` AND the case
   blocks terminal continuation. The controller treats clarification-linked
   `needs_operator` rows the same as the explicit boolean.
4. Validation result that requires human review (unknown trailer chip,
   wrong assignment, correction request, no order). The backend session API
   sets `needs_operator = true` + populates `issue_reason`.

The controller's `summary.needsOperator` count uses the combined predicate
`needs_operator = true OR session_state = 'needs_operator'`, so either
signal surfaces correctly.

---

## 7. Boundaries

- **No Bay Line panels.** Loading Control owns bay 1-6.
- **No remote controls.** No open gate / force exit / release driver / start /
  stop / PLC / ESD button anywhere.
- **No order assignment.** Use [`15-loading-orders.md`](15-loading-orders.md).
- **No quality / document decisions.** Use Analysis & Quality, Documents & Reports.
- **No device configuration.** Use System & Devices.
- **No bulk actions.** No DELETE. No export-by-selection.
- **`needsOperator` never inferred.** Frontend renders only when backend says so.

---

## 8. Error codes

| code | http | when |
|---|---|---|
| `TERMINAL_SESSION_NOT_FOUND` | 404 | session id does not exist |
| (validation envelope) | 422 | Laravel default — bad enum value on a filter, malformed date range |

There are no 409 / 423 codes in this slice — the read-only controller has no
state to conflict on.

---

## 9. Frontend notes

- **Touchpoint cards before the table.** V2.3 §3 makes this an architectural
  rule: the operator must not have to scan a table to understand state.
- **Disable unsafe controls everywhere.** If a future module adds gate / PLC
  controls, they belong on a separate page — this page must stay safety-clean.
- **Use `state.tone` from the resource for colour.** Never compute it on the
  frontend; the backend already maps it consistently with the design system.
- **Hide zero summary counters.** The backend already collapses them, so the
  FE simply renders what it receives — no `if value > 0` ladder needed.
- **`primarySessionId` is the deeplink target** from a touchpoint card — it
  always points at the same row the card describes.
- **`activeSessionCount > 1` ⇒ show a count chip + "View all" link** that
  filters `/sessions?touchpoint=<value>` and opens the table tab.
- **Action buttons on row menus only when the FK exists** (V2.3 §13). Show
  "Open Clarification" only when `clarificationCaseId` is set, etc.

---

## 10. Database schema

```sql
CREATE TABLE terminal_sessions (
  id                       CHAR(36) PRIMARY KEY,
  session_no               VARCHAR(50) NOT NULL UNIQUE,

  touchpoint               VARCHAR(30) NOT NULL,                 -- enum
  touchpoint_label         VARCHAR(100) NULL,

  device_id                CHAR(36) NULL,                        -- soft FK
  device_label             VARCHAR(100) NULL,
  device_health            VARCHAR(20)  NULL,                    -- online|warning|fault|offline|service_mode

  driver_id                CHAR(36) NULL,                        -- soft FK
  driver_name              VARCHAR(255) NULL,
  driver_code              VARCHAR(50)  NULL,

  plant_visit_id           CHAR(36) NULL,                        -- soft FK
  visit_no                 VARCHAR(50) NULL,

  order_id                 CHAR(36) NULL,
  order_no                 VARCHAR(50) NULL,

  trailer_id               CHAR(36) NULL,
  trailer_label            VARCHAR(100) NULL,

  current_screen           VARCHAR(40)  NULL,                    -- enum
  session_state            VARCHAR(30)  NOT NULL DEFAULT 'idle', -- enum (V2.3 §9)

  issue_reason             VARCHAR(500) NULL,
  action_needed            VARCHAR(255) NULL,

  needs_operator           TINYINT(1) NOT NULL DEFAULT 0,        -- backend-set; V2.3 §11
  support_requested        TINYINT(1) NOT NULL DEFAULT 0,
  clarification_case_id    CHAR(36) NULL,                        -- soft FK

  last_activity_at         TIMESTAMP NULL,

  correlation_id           VARCHAR(64) NULL,
  notes                    TEXT NULL,
  created_by_user_id       BIGINT UNSIGNED NULL,
  updated_by_user_id       BIGINT UNSIGNED NULL,
  created_at               TIMESTAMP NULL,
  updated_at               TIMESTAMP NULL,

  INDEX (touchpoint),
  INDEX (session_state),
  INDEX (current_screen),
  INDEX (driver_id),
  INDEX (plant_visit_id),
  INDEX (order_id),
  INDEX (needs_operator),
  INDEX (last_activity_at),
  INDEX (clarification_case_id),
  INDEX (touchpoint, session_state),
  INDEX (touchpoint, last_activity_at)
);
```

All FK-like columns are **soft** (no `FOREIGN KEY` constraints). Reasons:

- `device_id` points at a System & Devices row that does not exist yet.
- `plant_visit_id`, `order_id`, `trailer_id`, `clarification_case_id` may
  legitimately exist before/after their parent rows during the live demo.

---

## 11. Seeded demo data

`TerminalSessionSeeder` creates 8 rows covering every state across all 3
touchpoints. Driver / plant-visit / clarification back-references are
best-effort — when the parent seeder hasn't run, the row still seeds with
those FKs null so the page renders.

| session_no | touchpoint | state | notes |
|---|---|---|---|
| `TS-2026-0001` | entry_gate | `idle` | calm baseline |
| `TS-2026-0002` | driver_terminal | `active` | DRV-1001 on `trailer_identification` |
| `TS-2026-0003` | entry_gate | `denied` | DRV-1002, invalid chip/TAN |
| `TS-2026-0004` | driver_terminal | `needs_operator` | DRV-1003 on PV-2026-0022; `support_requested=true`; linked to CC-2026-0001 |
| `TS-2026-0005` | entry_gate | `device_fault` | reader fault — wins priority for the entry_gate card |
| `TS-2026-0006` | exit_gate | `service_mode` | exit gate B maintenance |
| `TS-2026-0007` | exit_gate | `active` | DRV-1005 on `exit_validation`, ready for exit |
| `TS-2026-0008` | driver_terminal | `active` | DRV-1002 on `task_selection` |

Resulting touchpoint board:

- **entry_gate** — `device_fault` wins over `denied` per V2.3 §6.3 priority
- **driver_terminal** — `needs_operator` wins over `active`
- **exit_gate** — `active` wins over `service_mode` (priority class 3 vs 4)
  — TS-2026-0007 is freshest

---

## 12. cURL cheatsheet

```bash
# Touchpoint board (3 cards always)
curl -s "http://localhost/api/gate-terminal-monitor/touchpoints" | jq .

# Paginated session list with summary
curl -s "http://localhost/api/gate-terminal-monitor/sessions?per_page=10" | jq .

# Filter: only sessions needing operator review
curl -s "http://localhost/api/gate-terminal-monitor/sessions?session_state=needs_operator" | jq .

# Filter: only entry_gate denied/blocked rows
curl -s "http://localhost/api/gate-terminal-monitor/sessions?touchpoint=entry_gate&session_state=denied" | jq .

# Single session detail
curl -s "http://localhost/api/gate-terminal-monitor/sessions/<uuid>" | jq .
```

---

## 13. Change log

- **v1 (2026-05-28)** — initial doc. Migration / model / 3 enums / 2 resources /
  read-only controller / seeder all live in the same commit. Write surface
  (start session / mark needs-operator / resolve / record fault) stays out of
  MVP per V2.3 §2.2; 5 `GATE_TERMINAL_*` audit constants are reserved for it.
