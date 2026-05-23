<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Integrations — SAP, OPC UA / PLC and Devices

## Integration principles

- Integrations must be safe by default.
- Failures must be visible through interface health, alarms/events and operational blockers.
- Do not silently continue critical workflows when required integration data is missing or stale.
- Use queue jobs and sync logs for retryable integration work.

## SAP integration

SAP provides loading orders and may receive process feedback.

Backend responsibilities:

- import loading orders,
- validate required fields,
- detect duplicates/conflicts,
- keep SAP references read-only where SAP owns them,
- expose SAP sync/order import status,
- send feedback such as actual quantity, completion, document status or process state where in scope,
- log sync results and errors.

Suggested tables:

- `loading_orders`
- `sap_order_imports`
- `integration_sync_logs`
- `sap_feedback_jobs`

Suggested services:

- `SapOrderImportService`
- `SapOrderMappingService`
- `SapFeedbackService`

## OPC UA / PLC integration

PLC/OPC UA data affects loading state, station health, actual quantity and fault visibility.

Backend responsibilities:

- capture station status and last heartbeat,
- record OPC/PLC events relevant to loading,
- block automatic release if communication is invalid for safety-critical steps,
- expose station/device health to Loading Control and Interface Health.

Do not put raw PLC low-level control values into ordinary table list endpoints unless explicitly required. Keep technical detail in device/station detail endpoints.

## Gate controllers

Gate controller events should create:

- gate event log,
- plant visit entry/exit state changes,
- validation result,
- alarm/event on failure or mismatch.

## Terminals and panels

Driver terminals and filling station panels require backend APIs for:

- identification,
- language-specific messages,
- action selection,
- tractor/trailer data capture,
- station validation,
- instruction display,
- event logging.

The dashboard Users/Roles system is not the same as driver terminal identity.

## Printers and documents

Backend should model:

- document generation,
- print job creation,
- print status,
- reprint reason/audit if required,
- printer failure events,
- exit blocking when mandatory documents are not printed/provided.

## Interface Health

Expose health status for:

- SAP,
- PLC/OPC UA,
- gate controllers,
- terminals/panels,
- card readers,
- printers,
- cloud sync if used.

Health status should include:

- status value/label/tone,
- last successful sync/heartbeat,
- last error summary,
- affected operational modules.
