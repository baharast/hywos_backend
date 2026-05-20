# HYWOS / FillTrack Backend AI Documentation Package

**Version:** v0.1  
**Audience:** AI coding assistants, backend developers, solution architects, QA engineers, and technical leads.  
**Backend stack correction:** The uploaded project documents sometimes mention ASP.NET Core. For this project package, **ignore ASP.NET references**. The backend must be implemented with **Laravel + MySQL**.  
**Frontend context:** The frontend is **Next.js / React / TypeScript** and consumes the Laravel API.

---

## 1. What this package is for

This package gives an AI assistant enough backend context to continue the HYWOS / FillTrack MVP safely and consistently.

The system is an industrial hydrogen loading management application. It manages:

- SAP loading orders.
- Drivers, tractors, trailers, chip cards, TAN fallback identification.
- Entry gate, driver terminal, filling station panel, and exit gate workflows.
- Trailer parking and pickup.
- Loading control.
- Pre-analysis and main-analysis quality decisions.
- Certificates, delivery notes, print jobs, and document lifecycle.
- Alarms, event journal, audit trail, interface health, and reporting.
- On-premise operation with integrations to SAP, PLC/OPC UA, gate controllers, printers, card readers, and optional cloud sync.

---

## 2. Recommended reading order for AI agents

Read these files in order before generating code:

1. `AGENTS.md`
2. `docs/context/00-project-ai-context.md`
3. `docs/product/01-mvp-scope-domain.md`
4. `docs/product/02-process-flows-business-rules.md`
5. `docs/technical/01-laravel-backend-architecture.md`
6. `docs/technical/02-database-model-mysql.md`
7. `docs/technical/03-api-design-contracts.md`
8. `docs/modules/01-driver-management-backend.md`
9. `docs/dev/01-backend-implementation-roadmap.md`

For integration work, also read:

- `docs/technical/05-integrations-sap-opcua-devices.md`

For testing and acceptance:

- `docs/qa/01-testing-acceptance.md`

---

## 3. Source-of-truth rules

When documents conflict, use this order:

1. Explicit correction in this package.
2. Process-flow document and functional concept rules.
3. ERD / database model.
4. Frontend UX specifications.
5. Open questions stay open. Do not silently decide them.

Important corrections:

- **Backend is Laravel, not ASP.NET.**
- **Database is MySQL.**
- **Frontend is Next.js.**
- **Driver terminal actions are exactly five in MVP:** Loading, Park trailer in parking area, Pick up a trailer, Print delivery note and exit, Exit.
- **Dispatching has four official variants:** Variant A park trailer, Variant B own trailer loading, Variant C pick up loaded trailer and exit, Variant D pick up empty trailer then load.
- **Bulk actions are not allowed in MVP.**
- **Manual overrides require reason, user, timestamp, before/after values, and often four-eyes approval.**
- **No auto-guessing is allowed for ambiguous driver/trailer/order matches.**
- **Functionally NOK main-analysis results may only be functionally approved by an Analysis Specialist.**

---

## 4. Suggested repository placement

Place this package in the Laravel backend repository:

```text
hywos-api/
├── AGENTS.md
├── docs/
│   ├── context/
│   ├── product/
│   ├── technical/
│   ├── modules/
│   ├── qa/
│   └── dev/
├── app/
├── routes/
├── database/
├── tests/
└── README.md
```

---

## 5. Best first prompt for an AI coding assistant

```text
Read AGENTS.md and the docs folder.

Important correction: the backend is Laravel + MySQL, not ASP.NET.

First summarize the project, then propose the Laravel backend implementation plan for Milestone 1: project foundation, auth/RBAC, base database migrations, audit/event logging foundation, and master-data APIs.

Do not generate code until you explain:
1. Which docs you used.
2. Which assumptions you made.
3. Which open questions remain.
4. Which files you plan to create or modify.
```
