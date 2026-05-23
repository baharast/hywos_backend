<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Database Model — MySQL Backend Guidance

This is a backend-oriented database guide, not the final migration dump. Use it to plan Laravel migrations and Eloquent relationships.

## Core modeling principles

- Prefer explicit domain tables over overloaded generic tables.
- Use stable UUIDs or ULIDs if the project wants API-safe public IDs.
- Keep internal numeric IDs hidden from frontend primary labels.
- Use controlled status enums or lookup tables.
- Use soft deletes or archived status for audited entities.
- Add `created_by`, `updated_by`, `blocked_by`, `activated_by` where relevant.
- Add audit tables for old/new values rather than relying only on timestamps.

## Suggested table groups

### User access

- `users`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`
- `password_resets` or framework equivalent
- `security_events` if in MVP scope

### Master data

- `drivers`
- `driver_identification_media` or `auth_media`
- `tans`
- `chip_cards`
- `customers`
- `freight_forwarders`
- `tractors`
- `trailers`
- `trailer_chips`

### Plant configuration

- `plant_configurations`
- `plant_areas`
- `gates`
- `terminals_panels`
- `filling_stations` or `bay_lines`
- `parking_areas`
- `parking_spaces`
- `plant_configuration_change_requests`
- `plant_configuration_versions` if version history is needed

### Operations

- `loading_orders`
- `plant_visits`
- `terminal_sessions`
- `gate_events`
- `trailer_pool_events`
- `station_reservations`
- `loading_operations`
- `clarification_cases`

### Analysis and quality

- `analysis_records`
- `analysis_results`
- `quality_decisions`
- `analysis_devices`
- `product_quality_specifications` where needed

### Documents and printing

- `documents`
- `certificates`
- `delivery_notes`
- `print_jobs`
- `document_templates`

### Events, audit and alarms

- `event_logs`
- `audit_logs`
- `alarms`
- `alarm_acknowledgements`

### Integrations and devices

- `integration_sync_logs`
- `sap_order_imports`
- `sap_feedback_jobs`
- `hardware_devices`
- `device_status_logs`
- `opcua_events`

## Required audit fields pattern

For auditable domain tables:

```text
id
uuid/public_id
status
created_at
updated_at
created_by
updated_by
archived_at
archived_by
```

For critical change logs:

```text
id
actor_user_id
entity_type
entity_id
action
old_values JSON
new_values JSON
reason TEXT NULL
ip_address NULL
user_agent NULL
created_at
```

## Plant Configuration lifecycle fields

`plant_configurations` should include:

- `status`: not_configured, draft, incomplete, pending_confirmation, active_locked, change_requested, inactive.
- `company_name`, `company_code`.
- `site_name`, `site_code`.
- `plant_type`.
- `default_language`.
- `time_zone`.
- `version`.
- `activated_at`, `activated_by`.
- validation summary/cache if useful.

## Driver fields

Drivers should include:

- driver code,
- first name,
- last name,
- preferred language,
- contact fields,
- license number/expiry,
- training/validity status,
- active flag,
- block status/reason,
- employer/operator company references,
- safe ID fields only; do not expose sensitive national ID hash in API.

## Customer fields

Customers should include:

- customer name/legal name,
- customer code,
- SAP customer number/reference,
- external reference,
- address fields,
- contact fields,
- document requirements JSON or relation table,
- status/block fields,
- notes.

## Loading Operation fields

A loading operation should include:

- loading reference,
- loading order,
- plant visit,
- station/bay line,
- driver,
- tractor,
- trailer,
- target quantity,
- actual quantity,
- progress percent or derived calculation,
- loading status,
- analysis status summary,
- started/completed timestamps,
- blocker/clarification flags.

## Indexing guidance

Add indexes for:

- public IDs/UUIDs.
- status fields.
- SAP references.
- driver code / customer code.
- active plant visit lookup.
- order number / SAP order number.
- station/bay status.
- event/audit entity type + entity id.
- created_at for logs.

## Do not model these as ordinary delete/update flows

- driver blocking,
- customer blocking,
- user disabling/locking,
- role permission critical changes,
- plant configuration structural changes after activation,
- quality decisions,
- manual overrides.

Use controlled action tables/events for these.
