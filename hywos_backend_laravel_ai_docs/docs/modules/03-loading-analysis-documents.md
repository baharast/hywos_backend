# Module 03 — Loading, Analysis, Documents and Exit Backend Specification

## 1. Purpose

This module controls the safety-critical part of the MVP:

- Filling station/panel authorization.
- Loading release.
- Loading measurement/progress tracking.
- Pre-analysis and main-analysis decisions.
- Document generation and printing.
- Exit eligibility.

---

## 2. Filling station/panel login

At the filling station panel, the driver identifies again.

Inputs:

- panel hardware ID,
- driver chip/TAN,
- trailer chip if configured,
- optional trailer plate,
- current Plant Visit.

Backend validates:

- driver,
- trailer,
- order operation,
- filling station assignment,
- plant visit status,
- driver/trailer eligibility,
- pre-analysis status,
- station/hardware availability.

If valid, return loading context:

- order number,
- target quantity,
- product/quality,
- station,
- release status,
- messages in driver language,
- blocking reasons if not released.

---

## 3. Loading operation

Create explicit `loading_operation` when the process reaches loading.

Important fields:

- order,
- operation,
- plant visit,
- driver,
- trailer,
- station,
- target quantity,
- actual quantity,
- status,
- release source,
- start/end times,
- correlation ID.

Statuses:

- `created`
- `waiting_for_pre_analysis`
- `pre_analysis_open`
- `released`
- `in_loading`
- `loading_completed`
- `waiting_for_main_analysis`
- `quality_checked`
- `quality_blocked`
- `documents_ready`
- `cancelled`
- `failed`

---

## 4. Loading release rule

Loading can be released only when:

- active order exists,
- order/operation/plant visit valid,
- driver active/not blocked,
- trailer active/not blocked/suitable,
- station assigned/available,
- no mismatch,
- pre-analysis OK or not required according to configuration,
- device gateway confirms station ready,
- no blocking clarification case.

Do not release loading if:

- driver at wrong station,
- trailer chip mismatch,
- order is incomplete/cancelled/blocked,
- SAP required update missing and rule says block,
- PLC/device unavailable,
- pre-analysis failed/technical fault,
- manual override missing reason/approval.

---

## 5. Pre-analysis

### 5.1 Attempt logic

- Maximum 3 attempts.
- Attempts stored as `quality_analysis` rows or as child attempt rows, but every attempt must be traceable.
- Distinguish functionally NOK from technically invalid.

### 5.2 Decision logic

| Condition | Result |
|---|---|
| OK | Release loading. |
| Not OK and attempts < 3 | Repeat allowed. |
| 3rd functionally NOK | Loading rejected; trailer blocked/clarification; alert. |
| 3rd technically invalid | Fault case; manual check; no release. |

---

## 6. Main analysis

### 6.1 Timing

Main analysis can happen:

- at 30% of loading,
- at 60%,
- at 90%,
- or after loading.

This must be configurable.

### 6.2 Decision logic

| Condition | Backend action |
|---|---|
| OK | Mark order/operation quality checked; allow documents. |
| Technically invalid first time | Allow exactly one technical repeat. |
| Technically invalid second time | Block completion; escalate. |
| Functionally NOK | Block documents/exit; no functional repetition; Analysis Specialist decision required. |

### 6.3 Analysis Specialist approval

Only Analysis Specialist can perform documented functional approval of functionally NOK main analysis.

Required:

- reason code,
- reason note,
- user,
- timestamp,
- measured values,
- affected order/trailer,
- audit log,
- event log,
- possibly four-eyes approval.

---

## 7. Documents

### 7.1 Document types

MVP may include:

- certificate,
- delivery note,
- QM document,
- ADR/transport documents if required.

### 7.2 Document generation

Generate documents only when prerequisites are satisfied.

Prerequisites:

- correct order/visit context,
- loading complete if document requires it,
- quality checked or approved,
- no blocking clarification,
- required data available,
- template configured.

### 7.3 Document lifecycle

Statuses:

- `pending`
- `generated`
- `print_queued`
- `printing`
- `printed`
- `print_failed`
- `reprinted`
- `handed_over`
- `archived`
- `blocked`

### 7.4 Print jobs

Every print attempt must be tracked.

Reprint requires:

- permission,
- reason,
- print job record,
- event log,
- audit log if reprint is sensitive.

---

## 8. Exit eligibility

Exit may be approved only when:

- Plant Visit exists.
- Driver identity matches visit.
- Trailer identity matches expected trailer if trailer leaves.
- Loading/order state allows exit.
- Quality state allows exit.
- Mandatory documents generated and provided.
- No unresolved blocking clarification.
- No active safety/security hold.
- Gate/device available or manual gate opening approved.

Exit denial must return blocking reasons.

Example blocking reasons:

- `QUALITY_NOT_CHECKED`
- `QUALITY_BLOCKED`
- `MANDATORY_DOCUMENT_NOT_PRINTED`
- `TRAILER_MISMATCH`
- `OPEN_CLARIFICATION_CASE`
- `ORDER_CANCELLED`
- `DRIVER_BLOCKED`
- `TRAILER_BLOCKED`
- `GATE_DEVICE_FAULT`

---

## 9. APIs

Loading:

```text
POST /api/filling-station/panel/login
POST /api/loading-operations/{id}/release
POST /api/loading-operations/{id}/start
POST /api/loading-operations/{id}/measurements
POST /api/loading-operations/{id}/complete
```

Analysis:

```text
GET  /api/quality/analyses
POST /api/quality/analyses/pre-analysis-result
POST /api/quality/analyses/main-analysis-result
POST /api/quality/analyses/{id}/approve
POST /api/quality/analyses/{id}/reject
```

Documents:

```text
GET  /api/documents
POST /api/orders/{orderId}/documents/generate
POST /api/documents/{documentId}/print
POST /api/documents/{documentId}/reprint
POST /api/documents/{documentId}/mark-handed-over
```

Exit:

```text
POST /api/terminal/gate/exit-identify
```

---

## 10. Tests

Required tests:

- Loading release denied when no active order.
- Loading release denied on driver/trailer/order mismatch.
- Loading release denied if driver blocked.
- Pre-analysis OK releases loading.
- Pre-analysis third functionally NOK blocks loading.
- Pre-analysis third technically invalid creates fault/clarification.
- Main analysis OK allows documents.
- Main analysis technically invalid allows one repeat only.
- Main analysis functionally NOK blocks documents/exit.
- Only Analysis Specialist can approve functionally NOK.
- Document generation blocked before quality approval.
- Print failure blocks exit.
- Reprint requires reason.
- Exit denied with missing document.
- Exit denied with wrong trailer.
- Exit approved closes Plant Visit when all conditions met.
