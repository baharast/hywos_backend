<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Project AI Context — HYWOS / FillTrack Backend

## Product summary

HYWOS / FillTrack is an industrial management system for hydrogen plant loading operations.
The MVP supports controlled workflows around drivers, trailers, tractor units, loading orders, plant visits, gates, terminals, filling stations/bay lines, analysis, documents, alarms, audit logs and external integrations.

The system is operationally sensitive. Backend decisions affect plant routing, loading release, quality/document readiness and exit permission.

## Backend target

This documentation package targets a Laravel backend with MySQL.
The backend exposes REST-style JSON APIs for a Next.js frontend.

Recommended Laravel structure:

- Controllers for request entry only.
- Form Requests for validation.
- Policies for authorization.
- Services for business/process logic.
- Models for persistence.
- API Resources for response shape.
- Events/Listeners for audit/event records.
- Jobs/Queues for SAP, print, device and sync work.
- Database transactions for multi-entity process changes.

## Core domain objects

The MVP backend should understand at least these object groups:

- Dashboard user accounts, roles and permissions.
- Drivers and identification media: chip cards and TANs.
- Customers and freight forwarders/carriers.
- Trailers, tractor units / vehicles and trailer chips.
- Loading orders imported from SAP or created manually where permitted.
- Plant visits as the operational glue between driver, tractor, trailer, order, gate, terminal and station activity.
- Filling stations / bay lines and station state.
- Parking areas and parking spaces / trailer pool state.
- Loading operations with target/actual quantity and progress state.
- Pre-analysis and main-analysis records.
- Quality decisions and analysis outcomes.
- Certificates, delivery notes, QM documents and print jobs.
- Alarms, event journal and audit trail.
- Company & Plant Configuration objects: company, site, areas, gates, terminals/panels, bay lines, parking areas/spaces.
- Integrations: SAP, OPC UA/PLC, gate controllers, terminals, panels, printers, card readers.

## MVP principle

The backend must prioritize traceability, safety, no-auto-guessing and auditability over convenience.

Examples:

- If a driver/trailer/order assignment is ambiguous, create/route a clarification case instead of guessing.
- If a structural plant configuration is active/locked, direct edits are blocked and a change request flow is required.
- If a user changes a critical state, require a reason and audit old/new values.
- If document prerequisites are not complete, block exit.

## Latest frontend alignment

The latest source pack defines frontend behavior for:

- Drivers
- Customers
- Loading Control
- Company & Plant Configuration
- Users
- Roles & Permissions
- Dashboard shell
- Core UI patterns
- Design system

The backend does not implement visual components, but it must provide the data, statuses, permissions, filters, actions and validation needed by those pages.

## Important naming note

The latest Dashboard Shell Template V3 uses the sidebar group label `Master Data` and route slugs such as `/master-data/drivers` and `/master-data/customers`.
Backend API route names may use `/api/drivers`, `/api/customers`, etc., but response objects must map clearly to those frontend routes.
