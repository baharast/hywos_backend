# AGENTS.md — Backend AI Coding Instructions for HYWOS / FillTrack

You are working on the **HYWOS / FillTrack backend**.

## 1. Mandatory stack

- Backend: **Laravel + PHP**
- Database: **MySQL**
- API consumer: **Next.js frontend**
- Runtime model: on-premise first, local server leads operational workflows
- Integrations: SAP, PLC/OPC UA or device gateway, gate controllers, printers, card readers, trailer-chip readers, optional cloud sync

Ignore all ASP.NET backend references in older source documents.

---

## 2. Engineering principles

Use a layered Laravel structure:

```text
app/
  Domain/
    Enums/
    ValueObjects/
    Services/
  Actions/
  DTO/
  Http/
    Controllers/Api/
    Requests/
    Resources/
  Models/
  Policies/
  Services/
    Audit/
    Events/
    Workflow/
    Reporting/
  Integrations/
    Sap/
    DeviceGateway/
    Printing/
    CloudSync/
  Jobs/
  Events/
  Listeners/
database/
  migrations/
  seeders/
tests/
  Feature/
  Unit/
```

Rules:

- Do not put business workflow logic directly in controllers.
- Use Form Requests for validation.
- Use API Resources for output shaping.
- Use service/action classes for use cases.
- Use explicit enums or enum-like classes for statuses.
- Use database transactions for state transitions.
- Record event logs for operational facts.
- Record audit logs for sensitive data/config/status changes.
- Use correlation IDs for all end-to-end process flows.
- Never expose raw sensitive data, passwords, full national IDs, or password hashes.
- Never expose `national_id_hash`.
- Do not hard-delete operational records. Use statuses/soft delete only when allowed.
- Do not implement bulk actions in MVP unless explicitly approved later.
- Do not invent SAP fields, PLC node IDs, or hardware protocols. Keep adapters abstract until confirmed.

---

## 3. Critical business rules

The following rules must be preserved in every implementation:

1. No loading may start without an active loading order.
2. Driver, trailer, order, station, and plant visit must be traceable.
3. If multiple orders/trailers/assignments could match, stop and open clarification. Do not guess.
4. Driver identification happens at the entry gate, driver terminal, and filling station panel when loading is released.
5. Driver chip is primary; TAN is fallback.
6. Trailer chip query is conditional on whether the project uses physical trailer chips.
7. Tractor-unit license plate must always be recorded or confirmed.
8. Changes after visit start must not rewrite historical execution snapshots.
9. Document printing and exit are blocked until loading, quality, and mandatory document prerequisites are fulfilled.
10. Functionally NOK main analysis blocks documents and exit unless an Analysis Specialist performs documented approval.
11. Operators may process technical faults but may not override product-quality decisions.
12. Every manual override requires reason, user, timestamp, and audit/event records.
13. Security, audit, and traceability are MVP core features, not optional add-ons.

---

## 4. Backend output expectations

When generating code:

- Include migrations, models, controllers, requests, resources, policies, services, factories, seeders, and tests where relevant.
- Keep route names predictable and REST-oriented.
- Use pagination and filtering for list APIs.
- Use structured error responses.
- Add tests for happy paths and blocking rules.
- Mark open questions clearly instead of guessing.
- Create small incremental changes that can be reviewed and run.

---

## 5. Before coding a module

Before coding any backend module, answer:

1. Which domain entities are touched?
2. Which statuses can change?
3. Which user role or permission can perform the action?
4. Does it require a reason?
5. Does it require audit log?
6. Does it create event log?
7. Does it affect documents, exit, loading release, or SAP feedback?
8. Does it need integration with a device or external system?
9. What validation prevents unsafe continuation?

Only then generate code.
