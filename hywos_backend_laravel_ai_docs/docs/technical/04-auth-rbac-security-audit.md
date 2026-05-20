# 04 — Authentication, RBAC, Security, Audit and Compliance

## 1. Security purpose

The backend manages safety-relevant industrial workflows. Security is not only login security; it is also operational control, traceability, role separation, and prevention of unauthorized quality or manual override decisions.

---

## 2. Authentication model

### 2.1 Internal users

Internal users include:

- Admin
- Dispatcher
- Operator
- Analysis Specialist
- Operations Manager
- IT/Support
- Auditor

Recommended:

- Laravel Sanctum for SPA/dashboard sessions if deployment topology supports it.
- Strong password rules.
- Lockout after repeated failed attempts.
- Session timeout.
- Security event logging.

### 2.2 Drivers

Drivers authenticate through:

- Driver chip card.
- TAN fallback.

Drivers do not use the management dashboard in MVP. They interact through terminal/panel/gate workflows.

### 2.3 Devices/service clients

Device gateway, SAP integration, printer callbacks, and cloud sync should use service credentials or signed requests.

Do not let device endpoints use normal user sessions.

---

## 3. RBAC model

### 3.1 Roles

| Role | Backend authority |
|---|---|
| Admin | Technical and functional configuration, users, roles, master data. |
| Dispatcher | Order assignment, scheduling, clarification, parking/station decisions. |
| Operator | Monitor and handle operational/device issues. |
| Analysis Specialist | Quality decisions and analysis rule ownership. |
| Operations Manager | KPI/reporting and management review. |
| IT/Support | Interface/device/system health and support actions. |
| Auditor | Read audit/event/report history. |
| Driver | Terminal/panel/gate self-service only. |

### 3.2 Permission categories

- `view`
- `create`
- `update`
- `block`
- `approve`
- `override`
- `print`
- `reprint`
- `configure`
- `export`
- `audit.view`

### 3.3 Critical permission rules

- Dispatchers cannot functionally approve product-quality NOK results.
- Operators cannot override product-quality decisions.
- Analysis Specialists can approve/reject quality decisions.
- Admin is not automatically a quality approver unless explicitly granted.
- Manual overrides may require four-eyes approval.
- Security/audit logs are read-only for most roles.

---

## 4. Sensitive actions

Sensitive actions must require:

- authenticated user,
- permission,
- reason code or note,
- timestamp,
- affected entity,
- old value,
- new value,
- correlation ID,
- audit log,
- sometimes second approval.

Examples:

- Block/unblock driver.
- Block/unblock trailer.
- Change assignment after driver confirmed.
- Manual gate opening.
- Manual loading release.
- Analysis Specialist functional approval.
- Document reprint.
- Role/permission changes.
- System/process/analysis configuration changes.
- Emergency bypass mode.

---

## 5. Four-eyes principle

Some actions should support a second approver.

Suggested fields for approval records or audit details:

- `requested_by_user_id`
- `approved_by_user_id`
- `requested_at`
- `approved_at`
- `reason_code`
- `reason_note`
- `entity_type`
- `entity_id`
- `old_value_json`
- `new_value_json`

Actions likely requiring four-eyes:

- Functional approval of functionally NOK main analysis.
- Permanent disabling of safety/audit-related rules.
- Emergency bypass that affects gate/loading release.
- Critical process configuration changes.
- Role/permission changes for high-privilege roles.

---

## 6. Audit log rules

### 6.1 What audit log stores

Audit log stores sensitive changes, not every operational event.

Required fields:

- entity name/type
- entity ID
- action
- old value JSON
- new value JSON
- changed by user
- changed at
- source IP
- user agent
- session ID
- correlation ID
- reason/note

### 6.2 Audit examples

Driver blocked:

```json
{
  "entity_name": "driver",
  "entity_id": "uuid",
  "action_name": "blocked",
  "old_value_json": {"block_status_code": "clear"},
  "new_value_json": {"block_status_code": "blocked", "reason_code": "TRAINING_EXPIRED"},
  "note": "Training expired."
}
```

Quality override:

```json
{
  "entity_name": "quality_analysis",
  "entity_id": "uuid",
  "action_name": "functional_approval",
  "old_value_json": {"result_status_code": "functionally_nok"},
  "new_value_json": {"decision": "approved_by_specialist"},
  "note": "Specialist approval after documented review."
}
```

---

## 7. Event log rules

Event log stores operational facts.

Examples:

- `GATE_ENTRY_APPROVED`
- `GATE_ENTRY_DENIED`
- `TERMINAL_LOGIN_SUCCESS`
- `TERMINAL_LOGIN_FAILED`
- `ORDER_MATCH_AMBIGUOUS`
- `TRAILER_PARKING_CONFIRMED`
- `LOADING_RELEASE_APPROVED`
- `LOADING_RELEASE_DENIED`
- `PRE_ANALYSIS_FAILED`
- `MAIN_ANALYSIS_FUNCTIONALLY_NOK`
- `DOCUMENT_PRINT_FAILED`
- `EXIT_DENIED`
- `EXIT_APPROVED`
- `SAP_SYNC_FAILED`
- `DEVICE_OFFLINE`

Event log should include contextual links where possible:

- site
- plant visit
- operation
- order
- driver
- tractor
- trailer
- hardware
- analysis
- certificate/document
- correlation ID

---

## 8. GDPR and personal data

The backend stores personal data such as driver names, contact details, identification media, language preference, license data, and visit times.

Rules:

- Store only data required for operations.
- Do not display full national IDs.
- Do not expose `national_id_hash`.
- Mask identifiers where possible.
- Define retention/anonymization strategy before production.
- Audit access to sensitive data if required.
- Use TLS for external communication.
- Encrypt backups.
- Do not log sensitive raw identifiers in plain text.

---

## 9. Security logs

Security events include:

- Failed login attempts.
- Account lock/unlock.
- Password changes.
- Permission/role changes.
- Failed access due to permission.
- Service credential failures.
- Suspicious repeated terminal/device authentication attempts.

Store in event log with security category or a dedicated security event table if later required.

---

## 10. Backend controls checklist

Before marking a module done:

- [ ] Auth required.
- [ ] Permissions enforced server-side.
- [ ] Sensitive fields hidden.
- [ ] Reason required for sensitive actions.
- [ ] Audit log created for sensitive changes.
- [ ] Event log created for operational facts.
- [ ] Status changes use service/action, not direct controller assignment.
- [ ] Tests cover denied/blocked/unauthorized cases.
- [ ] No hard delete of operational history.
- [ ] No auto-guessing in ambiguous workflows.
