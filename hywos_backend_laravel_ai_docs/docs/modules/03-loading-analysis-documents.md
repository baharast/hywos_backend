<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Module Backend Spec — Loading, Analysis and Documents

## Purpose

This backend area supports active loading operations, analysis decision states and document generation/printing.

It is the backbone behind Loading Control, Analysis & Quality and Certificates & Delivery Notes.

## Loading operation responsibilities

- Create or update active loading operation.
- Link loading to station/bay line, order, plant visit, driver, tractor and trailer.
- Track target quantity, actual quantity, progress, status, start/completion timestamps.
- Track station/device/PLC communication state.
- Expose blockers, alarms and clarification state.

## Analysis responsibilities

- Create pre-analysis record.
- Track attempts and result state.
- Release or block loading based on pre-analysis rules.
- Create main analysis record.
- Distinguish OK, functionally NOK and technically invalid.
- Require Analysis Specialist permission for critical decisions/manual approvals.

## Document responsibilities

- Generate certificates, delivery notes and QM documents where required.
- Store template version/language where applicable.
- Create print jobs.
- Track print, reprint, failure and handover status.
- Block exit while mandatory documents are not ready/printed/provided.

## Suggested API groups

```text
/api/loading-control/stations
/api/loading-control/loadings
/api/loading-control/loadings/{id}
/api/analysis-records
/api/quality-decisions
/api/documents
/api/documents/{id}/print
/api/documents/{id}/reprint
```

## Loading Control data shape

Station View needs:

- station/bay label,
- station status,
- current loading,
- driver/trailer,
- target quantity,
- actual quantity/progress,
- analysis summary,
- station health,
- related alarm count,
- last updated timestamp.

Active Loadings needs:

- loading id/status,
- station/bay,
- order/SAP order,
- driver/trailer,
- quantity progress,
- loading status,
- analysis status,
- alarms/blockers.

## Critical rules

- Wrong station blocks loading release.
- Trailer chip mismatch blocks loading release and opens clarification.
- PLC/OPC UA communication failure blocks automatic release.
- Pre-analysis failure after allowed attempts blocks release.
- Main analysis functionally NOK blocks documents and exit unless approved by authorized Analysis Specialist.
- Print failure must create visible document/print status and may block exit.

## Audit/events

Create event logs for:

- loading assigned,
- loading started,
- loading paused/completed/failed,
- analysis started/result received,
- quality decision,
- document generated,
- print job submitted/succeeded/failed,
- reprint.

Create audit logs for:

- manual quality decision,
- manual override,
- reprint reason if required,
- document status correction.

## Tests

- loading cannot start without valid assignment,
- wrong station returns conflict,
- analysis NOK blocks document readiness,
- quality approval requires permission,
- document generation creates print job and event,
- exit eligibility checks document state.
