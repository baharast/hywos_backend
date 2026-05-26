# Reports API

Backs **Documents & Reports → Reports** (FillTrack Reports UX Spec V2.1).
Read-only aggregation surface over the 9 MVP report families.

> Read [`00-conventions.md`](00-conventions.md) first.

---

## 0. Status of this module

| Layer | State |
|---|---|
| `ReportId` enum (9 values) | ✅ implemented |
| `ReportDateRangePreset` enum + `resolve()` helper | ✅ implemented |
| `ReportRegistry` (per-report metadata + availability flags) | ✅ implemented |
| `ReportsService` (one `build*()` method per report) | ✅ implemented |
| `ReportHubItemResource` | ✅ implemented |
| `ExportReportRequest` form request | ✅ implemented |
| `ReportsController` (hub / show / drill-down / export) | ✅ implemented |
| Routes — 4 endpoints under `/api/documents-reports/reports` | ✅ wired |
| Binary XLSX / PDF rendering | ⏳ deferred — export endpoint returns the report payload as JSON today and fires `report.exported` event |
| New tables created | ❌ none — this module is pure aggregation |

---

## 1. Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/documents-reports/reports` | Reports Hub — 9 reports + availability + `lastRefreshedAt` |
| GET | `/api/documents-reports/reports/{reportId}` | Full report detail (decisionStrip + visualization + drillDownRows) |
| GET | `/api/documents-reports/reports/{reportId}/drill-down` | Paginated drill-down rows only (for filter-driven table refreshes) |
| POST | `/api/documents-reports/reports/{reportId}/export` | Sync export; returns JSON payload + fires `report.exported` event |

**Not in scope** — POST/PUT/PATCH/DELETE on source tables, bulk endpoints,
row-selection, scheduled delivery, custom report builder. V2.1 §2.2 + §4.

---

## 2. `reportId` values

Both kebab-case (URL style) and snake_case (spec §14 TypeScript style) are
accepted on every endpoint that takes `{reportId}`. The canonical form
returned in responses is kebab-case.

| URL value | Snake-case alias | Spec ref |
|---|---|---|
| `daily-operations` | `daily_operations` | REP-01 |
| `loading-history` | `loading_history` / `order_execution` | REP-02 |
| `quantity-throughput` | `quantity_throughput` | REP-03 |
| `station-utilization` | `station_utilization` | REP-04 |
| `analysis-quality` | `analysis_quality` | REP-05 |
| `documents-print` | `documents_print` | REP-06 |
| `clarifications-alarms-audit` | `clarifications_alarms_audit` / `clarification_alarm_audit` | REP-07 |
| `gate-access-exit` | `gate_access_exit` | REP-08 |
| `device-health` | `device_health` / `interface_device_health` | REP-09 |

Unknown id → **404 `REPORT_NOT_FOUND`** with `details.allowed` enumerating
the 9 canonical kebab-case values.

---

## 3. Date range filter (shared)

Every report endpoint accepts these query (or body, for export) params:

| param | type | values |
|---|---|---|
| `range_preset` | string | `today` (default), `yesterday`, `this_week`, `last_week`, `this_month`, `last_month`, `last_7_days`, `last_30_days`, `custom` |
| `range_from` | ISO 8601 | required when `range_preset=custom` |
| `range_to` | ISO 8601 | required when `range_preset=custom`; must be `>= range_from` |

Server resolves the preset to `[from, to]` in server time (NOT browser time)
to keep audit-safe. Unknown presets fall back to `today` rather than 422 so
the FE never has to special-case "bad preset".

---

## 4. Hub — `GET /api/documents-reports/reports`

Returns 9 hub entries plus a summary block and `last_updated_at` computed
as the freshest `lastRefreshedAt` across all reports.

```json
{
  "message": "Reports hub retrieved",
  "data": [
    {
      "id": "daily-operations",
      "title": "Daily Operations Report",
      "category": "Operations overview",
      "purpose": "Shows if the day is normal or blocked.",
      "primaryUsers": ["Operations Manager", "Dispatcher", "Operator"],
      "defaultOutput": ["pdf", "xlsx"],
      "availability": "available",
      "dataSourceAvailable": true,
      "placeholderReason": null,
      "lastRefreshedAt": "2026-05-28T09:32:00+00:00",
      "routePath": "/documents-reports/reports/daily-operations"
    },
    /* ... 8 more rows ... */
  ],
  "summary": { "totalReports": 9 },
  "last_updated_at": "2026-05-28T09:32:00+00:00",
  "correlation_id": "..."
}
```

Two reports today carry `availability="not_ready"` / `availability="available"
+ dataSourceAvailable=false / partial` placeholder reasons:

- **REP-05 Analysis & Quality** — no analysis_results / quality_decisions tables exist.
- **REP-09 Interface & Device Health** — inventory only; per-device fault history not yet stored.

REP-07's Alarms tab is also empty pending an alarms table; the report
itself is `available` because its other tabs (Clarifications, Audit, Manual
Overrides) are live — `placeholderReason` documents the partial scope.

---

## 5. Detail — `GET /api/documents-reports/reports/{reportId}`

Single envelope, shape per V2.1 §7:

```json
{
  "message": "Report retrieved",
  "data": {
    "reportId": "documents-print",
    "title": "Documents & Print Report",
    "purpose": "...",
    "selectedRange": { "preset": "today", "from": "...", "to": "..." },
    "lastRefreshedAt": "...",
    "decisionStrip": [
      { "label": "Generated", "value": 12, "tone": "info" },
      { "label": "Print failures", "value": 1, "tone": "danger" }
    ],
    "primaryVisualization": {
      "type": "breakdown",
      "data": { /* per-report shape */ }
    },
    "drillDownRows": [ /* up to 200 rows; each row carries routePath */ ],
    "drillDownTotal": 14,
    "filters": { /* echo of applied filters */ }
  },
  "meta": {
    "dataSourceAvailable": true,
    "placeholderReason": null,
    "availability": "available"
  },
  "correlation_id": "..."
}
```

The `primaryVisualization.type` is one of `breakdown` | `trend` | `bars` |
`matrix` | `timeline` | `tabs` — each report picks the most useful shape per
V2.1 §11, the FE renders accordingly.

### 5.1 Per-report decisionStrip + visualization

| reportId | decisionStrip tiles | primaryVisualization.type | drill-down shape |
|---|---|---|---|
| `daily-operations` | completed / in-progress loadings, blockers, blocking documents, device faults, SAP failures | `breakdown` (output + blockers split) | completed loadings (LoadingOperation rows) |
| `loading-history` | orders-in-range / completed / blocked / cancelled counts | `timeline` (FE composes per-row vertical timeline from drill-down routePaths) | per-order rows including Customer + Carrier (V2.1 §12) |
| `quantity-throughput` | total loaded kg, target kg, completed loadings, avg per loading | `trend` (per-day actual + loadings) | grouped by (quality × customer × bay line) |
| `station-utilization` | active stations, total loadings, failed loadings | `bars` (per-station comparison) | per-bay-line counts + status |
| `analysis-quality` | empty (placeholder) | `breakdown` (placeholder note) | empty |
| `documents-print` | generated / printed / reprinted / handed-over / failures / exit-blocking counts | `breakdown` (lifecycle split) | OperationalDocument rows |
| `clarifications-alarms-audit` | open / critical / resolved / closed / manual overrides counts | `tabs` (clarifications / alarms / manual overrides / audit) | interleaved clarification + audit rows with `tab` discriminator |
| `gate-access-exit` | entry / exit sessions, entry denied, exit blocked | `breakdown` (top-10 denial reasons) | terminal_session rows |
| `device-health` | bay lines / gates / terminals counts + active faults | `matrix` (inventory rows + note) | currently-faulted terminal sessions |

### 5.2 Per-report filter keys

Each report's aggregator consumes whatever filter keys it knows and silently
ignores the rest. The controller doesn't validate per-report filter unions —
keep the FE migration story painless. Recognised keys:

| reportId | filters |
|---|---|
| `loading-history` | `order_search`, `customer_id`, `carrier_id`, `driver_id`, `trailer_id`, `status` |
| `quantity-throughput` | `product_quality`, `bay_line_id`, `customer_id` |
| `documents-print` | `document_type`, `print_status`, `exit_blocking` (bool) |
| `clarifications-alarms-audit` | `severity`, `status`, `owner_role` |
| `gate-access-exit` | `touchpoint`, `session_state` |
| others | none beyond `range_*` |

---

## 6. Drill-down — `GET /api/documents-reports/reports/{reportId}/drill-down`

Returns just the drill-down rows under the standard `ApiResponse::list()`
envelope — used by FE table-only refreshes after a filter change.

```http
GET /api/documents-reports/reports/documents-print/drill-down?per_page=25&page=2
```

```json
{
  "message": "Drill-down rows retrieved",
  "data": [ /* 25 rows */ ],
  "meta": { "current_page": 2, "per_page": 25, "total": 73, "last_page": 3 },
  "last_updated_at": "...",
  "correlation_id": "..."
}
```

Default `per_page=50`. Page indexing is 1-based.

---

## 7. Export — `POST /api/documents-reports/reports/{reportId}/export`

```http
POST /api/documents-reports/reports/documents-print/export
Content-Type: application/json

{
  "format": "xlsx",
  "range_preset": "this_week",
  "filters": { "document_type": "certificate", "exit_blocking": true }
}
```

Body fields: `format` (`pdf` | `xlsx` | `json` — default `json`),
`range_preset` / `range_from` / `range_to`, `filters` (object).

Response:

```json
{
  "message": "Report export prepared",
  "data": {
    "format": "xlsx",
    "report": { /* the full detail payload — same shape as §5 */ },
    "note": "Binary xlsx rendering not yet implemented; returning JSON payload for now."
  },
  "meta": { "dataSourceAvailable": true, "placeholderReason": null },
  "correlation_id": "..."
}
```

**Every successful call writes one `event_logs` row** with `event_type =
"report.exported"`, category `operations`, severity `info`, and a details
JSON capturing the report id, format, resolved range, filter set, and row
count. This is the audit trail for "who exported what when" until binary
rendering arrives.

No new audit constants are added — reports are reads, not state changes.

---

## 8. Error codes

| code | http | when |
|---|---|---|
| `REPORT_NOT_FOUND` | 404 | unknown `{reportId}` (details.allowed enumerates the 9 canonical ids) |
| (validation envelope) | 422 | malformed `range_from` / `range_to` / `format` on export |

---

## 9. Frontend notes

- **Compact hub** — `data[]` always has 9 rows. Render the FE selector from
  this list; the spec rejects large card galleries (V2.1 §6.1).
- **`dataSourceAvailable=false` → empty-state.** Render the placeholder
  reason as helper text under the report header, not as an error. The
  detail call still succeeds and returns a stable empty shape.
- **`primaryVisualization.type`** is the only switch the FE needs to pick
  the right chart component. The shape under `data` is report-specific.
- **`drillDownRows[i].routePath`** is the deeplink target — clicking a row
  navigates to the owning module's detail/list page with appropriate
  filter context (V2.1 §3 + §4). Never embed action buttons inline; this
  module is read-only.
- **Custom range** — when `range_preset=custom` the FE must collect start
  date/time + end date/time and POST both as ISO 8601 strings (V2.1 §8.1).
- **Freshness** — every detail response includes `lastRefreshedAt`; the
  hub response includes one per row. Show this above the chart.

---

## 10. Why no new tables

Reports aggregate from already-built modules. Adding a `reports_cache`
table is premature optimisation at MVP — the underlying tables are small
and indexed for the filters used here. When a report's drill-down starts
exceeding the 200-row cap, the right move is to expose a paginated source
endpoint on the owning module, not to materialise a new report table.

The only future write surface in this area is binary export job tracking
(see Master Data Export's `export_jobs` table for the pattern) — that would
land as `report_export_jobs` whenever PDF/XLSX rendering goes live.

---

## 11. cURL cheatsheet

```bash
# Hub
curl -s "http://localhost/api/documents-reports/reports" | jq .

# Detail with default `today` range
curl -s "http://localhost/api/documents-reports/reports/documents-print" | jq .

# Detail with this-week + filter
curl -s "http://localhost/api/documents-reports/reports/documents-print?range_preset=this_week&document_type=certificate" | jq .

# Custom range
curl -s "http://localhost/api/documents-reports/reports/quantity-throughput?range_preset=custom&range_from=2026-05-01T00:00:00Z&range_to=2026-05-28T23:59:59Z" | jq .

# Drill-down only (page 2 / 25)
curl -s "http://localhost/api/documents-reports/reports/loading-history/drill-down?page=2&per_page=25" | jq .

# Export (JSON today)
curl -s -X POST "http://localhost/api/documents-reports/reports/daily-operations/export" \
  -H "Content-Type: application/json" \
  -d '{"format":"xlsx","range_preset":"today"}' | jq .
```

---

## 12. Change log

- **v1 (2026-05-28)** — initial implementation. 4 endpoints, 9 report build
  methods, hub registry with availability flags, sync JSON export +
  `report.exported` event log entry. No new tables, no source mutations.
