<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Module Backend Spec — Driver Management

## Frontend source alignment

Source: `FillTrack Driver Management UX Frontend Spec -EN-V2.md`

Frontend route group:

- `/master-data/drivers`
- `/master-data/drivers/new`
- `/master-data/drivers/:driverId`
- `/master-data/drivers/:driverId/edit`

Backend route group suggestion:

- `GET /api/drivers`
- `POST /api/drivers`
- `GET /api/drivers/{driver}`
- `PUT /api/drivers/{driver}`
- `POST /api/drivers/{driver}/block`
- `POST /api/drivers/{driver}/unblock`
- `POST /api/drivers/{driver}/tan`
- `GET /api/drivers/{driver}/plant-visits-orders`
- `GET /api/drivers/{driver}/events-audit`
- `POST /api/drivers/export`

## Purpose

Driver Management controls driver master data and operational eligibility.
It does not run the live workflow itself; it provides the data required by gate, terminal, loading, plant visit and clarification workflows.

## Key backend responsibilities

- Maintain driver identity and contact data.
- Store preferred driver language for terminal messages.
- Store training/validity and license state.
- Expose chip/TAN identification summary.
- Track active/recent plant visit context.
- Support block/unblock with reason.
- Provide related plant visits/orders, clarifications, event/audit records.
- Enforce no hard delete.

## Core data fields

- `driver_code`
- `first_name`
- `last_name`
- `national_id_last4`
- `license_no`
- `license_expiry_date`
- `phone`
- `email`
- `preferred_culture`
- `training_status`
- `is_active`
- `block_status`
- `block_reason`
- `employer_company_id`
- `operator_company_id`
- `notes`

Do not expose `national_id_hash` in UI resources.

## Search and filters

Search target:

- first name,
- last name,
- driver code,
- license number,
- phone,
- email,
- company display name,
- chip/TAN identifier display value,
- active plant visit number.

Primary filters:

- status,
- training / validity,
- identification,
- language,
- attention.

Secondary filters:

- employer company,
- operator company,
- last visit date range,
- active plant visit only,
- created/updated range.

## Block/unblock behavior

`POST /api/drivers/{driver}/block`

- Requires permission.
- Requires reason.
- Does not silently cancel existing active orders.
- Must create audit record.
- Must create event or attention state if driver has active plant visit.

`POST /api/drivers/{driver}/unblock`

- Requires permission.
- Requires reason.
- Must validate that no other blocking condition remains.
- Must create audit record.

## Response shaping

Driver list rows should expose:

- driver name and code,
- status badge object,
- training/validity badge,
- license expiry state,
- language,
- identification summary,
- last/active visit,
- row actions.

## Detail tabs

- Overview.
- Identification.
- Plant Visits & Orders.
- Clarification.
- Events & Audit.
- Notes.

## Validation rules

- first name required,
- last name required,
- driver code required unless backend generates it,
- preferred language required,
- email valid if entered,
- block status changes must use block/unblock action endpoint.

## Tests

Add feature tests for:

- list filters,
- create/update validation,
- block/unblock requires reason,
- unauthorized block denied,
- national_id_hash never appears in response,
- active visit relation exposed,
- export respects visible columns.
