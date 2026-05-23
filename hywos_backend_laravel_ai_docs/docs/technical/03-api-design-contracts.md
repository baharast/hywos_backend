<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# API Design Contracts

## Base API conventions

Use REST-style JSON endpoints with predictable response shapes.

```text
GET    /api/drivers
POST   /api/drivers
GET    /api/drivers/{driver}
PUT    /api/drivers/{driver}
POST   /api/drivers/{driver}/block
POST   /api/drivers/{driver}/unblock
```

Use similar action endpoints for critical operations instead of raw status patching.

## List endpoint contract

List endpoints should support:

- search query,
- primary filters,
- secondary filters,
- sort,
- pagination,
- optional summary cards,
- authorized actions metadata.

Example request:

```http
GET /api/drivers?search=chip123&status=active&identification=missing&page=1&per_page=8&sort=last_name
```

Example response:

```json
{
  "data": [
    {
      "id": "drv_01",
      "driverCode": "D-1001",
      "fullName": "Max Driver",
      "status": { "value": "active", "label": "Active", "tone": "success" },
      "language": "DE",
      "identification": { "label": "Chip assigned", "tone": "success" },
      "actions": [
        { "key": "view", "enabled": true },
        { "key": "edit", "enabled": true },
        { "key": "block", "enabled": true, "requiresReason": true }
      ]
    }
  ],
  "summary": {
    "totalDrivers": 248,
    "activeDrivers": 231,
    "blockedDrivers": 4,
    "needsAttention": 13
  },
  "meta": {
    "page": 1,
    "perPage": 8,
    "total": 248,
    "firstVisibleRow": 1,
    "lastVisibleRow": 8
  },
  "lastUpdatedAt": "2026-05-23T08:00:00Z"
}
```

## Detail endpoint contract

Detail responses should support frontend tabs.

Options:

1. Return all tab summaries in one endpoint for low/medium data volume.
2. Return detail header plus separate tab endpoints for heavy logs/tables.

Example:

```text
GET /api/drivers/{driver}
GET /api/drivers/{driver}/plant-visits-orders
GET /api/drivers/{driver}/events-audit
```

## Critical action endpoint contract

Critical action request:

```json
{
  "reason": "Driver license expired and gate access must be blocked.",
  "context": {
    "source": "driver_detail"
  }
}
```

Critical action response:

```json
{
  "success": true,
  "message": "Driver blocked. The change was added to the audit trail.",
  "data": {},
  "auditEventId": "aud_123"
}
```

## Export endpoint contract

Export requests must include visible columns.

```http
POST /api/customers/export
```

```json
{
  "search": "Schweinfurt",
  "filters": { "status": "active" },
  "visibleColumns": ["customer", "sapReference", "status", "location", "openOrders"]
}
```

Rules:

- No row selection required.
- No bulk selection behavior.
- Respect filters and search.
- Include only requested visible columns.
- Use queued export if result size is high.

## Status object standard

Use consistent status objects:

```json
{
  "value": "active_locked",
  "label": "Active / Locked",
  "tone": "success"
}
```

Allowed `tone` values:

- `success`
- `info`
- `warning`
- `orange`
- `danger`
- `maintenance`
- `offline`
- `neutral`

## Error responses

Use Laravel's validation response for `422`.

Business conflict example:

```json
{
  "message": "Loading release is blocked because the trailer does not match the assigned order.",
  "errorCode": "ASSIGNMENT_MISMATCH",
  "details": {
    "plantVisitId": "pv_123",
    "orderId": "ord_456",
    "trailerId": "trl_789"
  },
  "nextAction": {
    "type": "open_clarification_case",
    "label": "Open clarification case"
  }
}
```

Use `409` for business conflicts, `423` for locked configuration/resources and `503` for unavailable integrations.
