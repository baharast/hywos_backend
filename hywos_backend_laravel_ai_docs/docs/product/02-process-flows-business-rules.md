<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Process Flows and Business Rules

## End-to-end MVP flow

1. SAP provides or updates a loading order.
2. Driver arrives at the entry gate and identifies by chip card or TAN.
3. Driver checks in at the driver terminal.
4. System records tractor plate and trailer identity.
5. Backend matches driver, trailer, tractor and order context.
6. Backend chooses or confirms the correct process path and next instruction.
7. Driver parks trailer, picks up trailer, goes to bay line, prints documents or exits depending on process state.
8. At filling station / panel, backend validates assignment and station compatibility.
9. Pre-analysis controls loading release.
10. Loading operation captures target, actual quantity, station status, PLC/OPC UA data and timestamps.
11. Main analysis/quality decision controls document readiness and exit eligibility.
12. Documents are generated/printed/reprinted and archived.
13. Exit gate validates completed prerequisites before closing plant visit.
14. SAP feedback/status export is queued or sent according to integration design.

## Standard driver terminal actions

The driver-facing terminal must not be implemented as dashboard role logic. It is an operational/kiosk flow.

Supported actions:

- Loading.
- Pick up full trailer and leave / pick up trailer.
- Go to parking area.
- Print documents and exit.
- Exit.

Backend must interpret these actions based on plant visit/order/trailer state and not treat them as arbitrary shortcuts.

## Core business rules

### Driver validation

- Driver must be active and not blocked.
- Driver identification medium must be valid.
- Driver preferred language should be used for terminal messages; fallback language is allowed.
- Expired or missing eligibility states should block or create attention/clarification according to rules.

### Trailer/order validation

- Trailer must match the assigned order or process variant.
- Trailer chip mismatch must block loading release.
- Wrong trailer/order/station combination creates clarification and event record.

### Loading release

Loading release requires:

- active plant visit,
- active loading order,
- valid driver,
- valid trailer/order assignment,
- correct station/bay context,
- valid pre-analysis decision where required,
- no critical active blocker.

### Analysis and documents

- Pre-analysis can release or block loading according to retry/result rules.
- Main analysis controls quality checked / documents blocked / documents ready state.
- Functionally NOK and technically invalid must be separated.
- Document printing and exit remain blocked until mandatory prerequisites are complete.

### Manual overrides

Manual override must capture:

- actor,
- timestamp,
- affected entity,
- previous value,
- new value,
- reason,
- optional approval/four-eyes data if later required.

### Configuration lifecycle

Company & Plant Configuration must follow:

1. Not configured.
2. Draft.
3. Incomplete / pending confirmation.
4. Active / locked.
5. Change requested / applied through controlled flow.

## Event vs audit distinction

Use event logs for operational happenings:

- gate entry,
- terminal login,
- station assignment,
- loading started/completed,
- analysis result received,
- document generated,
- printer failed,
- exit completed.

Use audit logs for user/system changes to data/configuration/permissions:

- driver blocked/unblocked,
- customer blocked/unblocked,
- role permission changed,
- user disabled,
- plant configuration activated,
- structural change request applied.
