<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Backend Status Checklist

Use this file as a living checklist while implementing the Laravel backend.

## Foundation

- [ ] Laravel project created.
- [ ] MySQL configured.
- [ ] Auth configured.
- [ ] API route prefix created.
- [ ] Base API response/resource pattern established.
- [ ] AuditLog model/table/service created.
- [ ] EventLog model/table/service created.
- [ ] Queue configured.
- [ ] Error code convention implemented.

## Access Control

- [ ] Users model/API implemented.
- [ ] Roles model/API implemented.
- [ ] Permissions model/API implemented.
- [ ] Policies/Gates implemented.
- [ ] Critical permission audit logging implemented.
- [ ] Self-lockout prevention implemented.

## Company & Plant Configuration

- [ ] Draft configuration endpoints.
- [ ] Areas/zones.
- [ ] Gates.
- [ ] Terminals/panels.
- [ ] Bay lines.
- [ ] Parking areas/spaces.
- [ ] Validation endpoint.
- [ ] Review endpoint.
- [ ] Activation lock.
- [ ] Change requests after activation.

## Master Data

- [ ] Drivers list/detail/create/edit.
- [ ] Driver block/unblock.
- [ ] Driver chip/TAN summary.
- [ ] Customers list/detail/create/edit.
- [ ] Customer block/unblock.
- [ ] Trailers.
- [ ] Tractors/vehicles.
- [ ] Chip Cards.
- [ ] TANs.

## Operations

- [ ] Loading orders.
- [ ] Plant visits.
- [ ] Driver terminal API foundations.
- [ ] Gate event foundations.
- [ ] Clarification cases.
- [ ] Trailer pool / car park.
- [ ] Loading Control Station View.
- [ ] Loading Control Active Loadings.

## Analysis & Documents

- [ ] Pre-analysis records.
- [ ] Main analysis records.
- [ ] Quality decisions.
- [ ] Document records.
- [ ] Certificate/delivery note generation state.
- [ ] Print job tracking.
- [ ] Exit eligibility checks.

## Integrations

- [ ] SAP sync logs.
- [ ] SAP order import placeholder/service.
- [ ] SAP feedback jobs placeholder/service.
- [ ] OPC UA/PLC status abstraction.
- [ ] Device/interface health endpoints.
- [ ] Printer status / print queue abstraction.

## QA

- [ ] Feature tests for each CRUD module.
- [ ] Policy tests.
- [ ] Critical action reason tests.
- [ ] Audit old/new value tests.
- [ ] No hard delete tests.
- [ ] Export visible columns tests.
- [ ] Ambiguous assignment tests.
- [ ] Locked configuration tests.
