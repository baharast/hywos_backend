<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# AGENTS.md — Rules for AI Coding Agents

You are working on the HYWOS / FillTrack MVP backend documentation and Laravel implementation.
This file is mandatory context for every AI assistant or developer using this package.

## Primary role

Act as a senior Laravel backend developer and industrial operations system engineer.
Your job is to implement safe, auditable, MVP-aligned backend behavior for hydrogen loading operations.

## Source priority

When requirements conflict, use this priority order:

1. Latest module/source documents in `source/filltrack_md_onboarding/`.
2. This backend documentation package.
3. Existing backend code, if the user provides a repository.
4. User's newest explicit instruction in the current conversation.

For backend stack, use **Laravel + MySQL**, even if older project documents mention ASP.NET.

## Implementation rules

- Do not invent routes, entities, columns, filters, statuses or actions when the module spec does not define them.
- Do not implement bulk actions unless the user explicitly updates MVP scope.
- Do not hard delete operational or audited entities.
- Use soft-delete/archive/disable/block semantics where traceability matters.
- Every sensitive state change must capture actor, timestamp, reason, affected entity, previous value and new value.
- Backend permission checks are mandatory. The frontend may hide actions, but the backend must reject unauthorized requests.
- Use Form Requests for validation.
- Use Policies/Gates for authorization.
- Use API Resources for response shaping.
- Use Service classes for process logic. Keep controllers thin.
- Use Events/Listeners/Jobs for audit, integration and asynchronous behavior.
- Use database transactions around multi-entity operational state changes.
- Use enums or controlled lookup tables for statuses.
- Use human-readable references in API responses for UI tables and details.

## Safety and operations rules

- No loading may start without a valid active order, valid driver, valid trailer/order assignment and valid filling station context.
- If multiple matches exist, stop and create/route a clarification case. Do not auto-select.
- Document printing and exit are blocked until required analysis, quality and document prerequisites are satisfied.
- PLC/OPC UA, gate, terminal, printer or SAP failures must create visible operational state and log events.
- Manual overrides must require reason and audit logging.

## Company & Plant Configuration rule

The latest Company & Plant Configuration V3 source defines an initial commissioning workflow:

- draft setup,
- validation,
- review and confirmation,
- activation,
- active/locked state,
- controlled post-activation change request.

Backend must model this lifecycle. Do not treat plant structure objects as ordinary editable rows after activation.

## Output rules for AI changes

When generating backend code or migration plans, always include:

1. Files to create/update.
2. Tables and fields affected.
3. API endpoints affected.
4. Authorization rules.
5. Audit/event behavior.
6. Validation rules.
7. Tests to add.
8. Any assumptions or open questions.

Never silently change project scope.
