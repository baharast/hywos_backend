<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Module Backend Spec — Orders, Dispatching and Plant Visits

## Purpose

This module connects SAP loading orders, driver/trailer/tractor assignments, process variants and plant visits.
Plant Visit is the operational glue that keeps the process traceable from entry to exit.

## Main backend responsibilities

- Import and maintain loading orders.
- Link orders to customer/carrier/product/quality/target quantity.
- Create/update plant visits when a driver enters or registers.
- Match driver, tractor, trailer and order.
- Route to parking, loading, pickup, document printing or exit.
- Create clarification when data is ambiguous or mismatched.
- Keep execution snapshot immutable for history/documents.

## Suggested API groups

```text
/api/loading-orders
/api/loading-orders/{id}
/api/plant-visits
/api/plant-visits/{id}
/api/plant-visits/{id}/events
/api/clarification-cases
```

## Loading order fields

- internal order id/reference,
- SAP order number,
- customer,
- freight forwarder/carrier,
- product/quality,
- target quantity,
- tolerance,
- planned time window,
- status,
- assigned driver,
- assigned tractor,
- assigned trailer,
- SAP sync state.

## Plant visit fields

- visit number,
- driver,
- tractor plate snapshot,
- trailer snapshot,
- order context,
- current instruction,
- visit status,
- entry timestamp,
- exit timestamp,
- current station/parking context,
- clarification state,
- document/analysis state summaries.

## No-auto-guessing rules

Create or route a clarification case when:

- multiple orders match the same driver/trailer,
- trailer chip does not match assigned trailer,
- station does not match assignment,
- tractor/trailer data is missing or changed unexpectedly,
- order/customer/carrier relation is inconsistent,
- mandatory order fields from SAP are missing.

## Snapshot rule

If tractor plate or trailer plate changes after a visit starts, previous order history, certificates and delivery-note execution snapshots must not be changed retroactively.
Store snapshot fields for documents/process records.

## Status examples

Plant Visit:

- `entered`
- `registered`
- `assigned`
- `parking_instruction_given`
- `waiting_for_loading`
- `loading`
- `waiting_for_documents`
- `ready_for_exit`
- `closed`
- `blocked`
- `clarification_required`

Loading Order:

- `imported`
- `assigned`
- `in_process`
- `in_loading`
- `quality_check_open`
- `quality_checked`
- `documents_ready`
- `completed`
- `blocked`
- `cancelled`

## Tests

- ambiguous order match creates clarification,
- wrong trailer blocks release,
- plant visit requires driver identity,
- exit blocked if documents not ready,
- snapshots remain unchanged after plate edit,
- SAP-owned fields cannot be edited unless correction flow permits.
