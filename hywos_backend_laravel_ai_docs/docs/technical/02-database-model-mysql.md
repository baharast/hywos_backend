# 02 — Database Model and MySQL Implementation Guide

## 1. Purpose

This document explains the backend database model in Laravel/MySQL terms.

The ERD contains the core tables needed for the MVP, but some practical implementation tables should be added before development, especially for parking, documents, loading operations, clarification cases, integration messages, and status catalogs.

---

## 2. Database conventions

### 2.1 Primary keys

Use:

- `char(36)` UUIDs for business entities.
- `bigint unsigned auto_increment` for high-volume logs/history when appropriate.

Examples:

- `driver.driver_id` = UUID.
- `event_log.event_id` = bigint.
- `audit_log.audit_id` = bigint.

### 2.2 Common columns

Most business tables should include:

- `created_at`
- `created_by_user_id`
- `updated_at`
- `updated_by_user_id`
- optional `is_active`
- optional `is_archived`

### 2.3 Status columns

Use `varchar(30)` or `varchar(50)` status columns initially. Keep values controlled by enums in Laravel.

Later, a lookup/status catalog can be introduced if needed. Do not let arbitrary status strings appear from controllers.

### 2.4 JSON columns

The ERD uses JSON for flexible data:

- `properties_json`
- `characteristics_json`
- `configuration_json`
- `metadata_json`
- `details_json`
- `old_value_json`
- `new_value_json`
- `result_json`

Use JSON only where flexible or vendor-specific data is expected. Do not hide core workflow state only inside JSON.

---

## 3. Core ERD tables

### 3.1 Location and organization

#### `address`

Stores addresses for sites and companies.

Main fields:

- `address_id`
- `line1`
- `line2`
- `city`
- `state_province`
- `postal_code`
- `country`
- `latitude`
- `longitude`

#### `site`

Represents a physical plant/site.

Main fields:

- `site_id`
- `code`
- `name`
- `address_id`
- `time_zone`
- `operator_company_id`
- `is_active`

#### `plant_area`

Represents areas inside a site.

Main fields:

- `plant_area_id`
- `site_id`
- `code`
- `name`
- `description`
- `is_active`

#### `company`

Generic company table for customers, operators, owners, freight forwarders, carriers, source/destination companies.

Main fields:

- `company_id`
- `company_code`
- `name`
- `legal_name`
- `registration_no`
- `tax_id`
- `vat_no`
- `industry`
- `phone`
- `email`
- `website`
- `parent_company_id`
- `address_id`
- `erp_code`
- `sap_code`
- `notes`
- `is_active`

#### `company_role_assignment`

Allows one company to act as customer, freight forwarder, operator, carrier, owner, etc.

Main fields:

- `company_role_assignment_id`
- `company_id`
- `role_code`
- `valid_from`
- `valid_to`
- `notes`

#### `customer_profile`

Customer-specific fields for a company.

Main fields:

- `company_id`
- `customer_code`
- `customer_type_code`
- `credit_limit`
- `payment_terms`
- `notes`

#### `freight_forwarder_profile`

Freight-forwarder/carrier-specific fields.

Main fields:

- `company_id`
- `forwarder_code`
- `license_no`
- `notes`

---

## 4. Security and users

### 4.1 `user_account`

Internal dashboard/system user.

Main fields:

- `user_id`
- `username`
- `full_name`
- `email`
- `phone`
- `password_hash`
- `preferred_culture_code`
- `company_id`
- `is_active`
- `is_locked`
- `last_login_at`

Laravel note:

- If using Laravel's default `users` table, align carefully or create a custom `User` model using `user_account`.
- Never store plain text passwords.
- If using Sanctum, configure model accordingly.

### 4.2 `role`

Main fields:

- `role_id`
- `code`
- `name`
- `description`
- `is_active`

### 4.3 `permission`

Main fields:

- `permission_id`
- `code`
- `name`
- `module`
- `description`
- `is_active`

### 4.4 `user_role`

Assigns roles to users.

### 4.5 `role_permission`

Assigns permissions to roles.

Implementation rules:

- Permission checks must be enforced in backend.
- Audit role/permission changes.
- Seed MVP roles and permissions.

---

## 5. Files and attachments

### 5.1 `file_asset`

Stores uploaded/generated file metadata.

Main fields:

- `file_asset_id`
- `file_code`
- `original_filename`
- `mime_type`
- `file_size_bytes`
- `storage_provider`
- `storage_path`
- `public_url`
- `checksum_sha256`
- `is_image`
- `uploaded_by_user_id`
- `uploaded_at`
- `is_archived`
- `metadata_json`

### 5.2 `entity_file`

Generic attachment link.

Main fields:

- `entity_file_id`
- `file_asset_id`
- `entity_type`
- `entity_id`
- `purpose`
- `display_order`
- `caption`
- `is_primary`
- `attached_at`
- `attached_by_user_id`

Rules:

- Use for driver/avatar/attachments, order documents, certificates, templates, etc.
- Do not use files as the only source of structured document state.

---

## 6. Drivers, tractors, trailers, fleets

### 6.1 `driver`

Main fields from ERD:

- `driver_id`
- `driver_code`
- `first_name`
- `last_name`
- `national_id_hash`
- `national_id_last4`
- `license_no`
- `license_expiry_date`
- `phone`
- `email`
- `preferred_culture_code`
- `employer_company_id`
- `operator_company_id`
- `is_active`
- `notes`

Recommended additions for MVP/frontend/backend:

- `block_status_code`
- `block_reason`
- `blocked_at`
- `blocked_by_user_id`
- `training_status_code`
- `training_valid_until`
- `avatar_file_id`
- `last_verified_at`

Security:

- Never expose `national_id_hash`.
- Show only `national_id_last4`.

### 6.2 `tractor`

Main fields:

- `tractor_id`
- `tractor_code`
- `license_plate`
- `registration_country`
- `vin`
- `brand`
- `model`
- `vehicle_type`
- `capacity_kg`
- `owner_company_id`
- `operator_company_id`
- `home_site_id`
- `last_inspection_date`
- `is_active`

### 6.3 `trailer`

Main fields:

- `trailer_id`
- `trailer_code`
- `license_plate`
- `registration_country`
- `serial_no`
- `trailer_type`
- `capacity_kg`
- `capacity_liter`
- `owner_company_id`
- `operator_company_id`
- `home_site_id`
- `last_inspection_date`
- `characteristics_json`
- `is_active`

Recommended additions:

- `trailer_status_code`
- `block_status_code`
- `block_reason`
- `current_site_id`
- `current_parking_space_id`
- `current_product_status_code`
- `last_known_location_at`
- `inspection_valid_until`
- `approved_product_json` or normalized suitability table if required.

### 6.4 `vehicle_fleet` and `vehicle_fleet_member`

Supports grouping drivers/tractors/trailers.

### 6.5 `tractor_trailer_coupling`

Tracks coupling between tractor and trailer, optionally linked to Plant Visit.

Main fields:

- `coupling_id`
- `tractor_id`
- `trailer_id`
- `plant_visit_id`
- `coupling_source_code`
- `coupled_at`
- `decoupled_at`
- `notes`

Rules:

- Use for Variant C/D pickup.
- Use for exit verification.
- Do not overwrite historical coupling when master data changes.

---

## 7. Authentication media

### 7.1 `auth_medium`

Represents chip cards, TANs, trailer chips, badges, hardware tokens.

Main fields:

- `auth_medium_id`
- `medium_type_code`
- `identifier_value`
- `identifier_hash`
- `driver_id`
- `tractor_id`
- `trailer_id`
- `hardware_object_id`
- `issued_at`
- `expires_at`
- `is_active`

Recommended additions:

- `status_code`
- `is_single_use`
- `used_at`
- `used_by_visit_id`
- `issued_by_user_id`
- `revoked_at`
- `revoked_by_user_id`
- `revocation_reason`
- `order_id`
- `operation_id`

Rules:

- Store hashed identifiers where possible.
- Mask display values.
- TAN should support expiry and one-time-use.
- Invalid/expired/blocked media must block gate/terminal/panel decisions.

---

## 8. Hardware and devices

### 8.1 `hardware_object`

Represents terminals, panels, printers, readers, PLC endpoints, gate controllers, etc.

Main fields:

- `hardware_object_id`
- `hardware_code`
- `name`
- `hardware_type_code`
- `site_id`
- `plant_area_id`
- `operator_company_id`
- `address_id`
- `serial_number`
- `manufacturer`
- `model`
- `firmware_version`
- `software_version`
- `ip_address`
- `mac_address`
- `interface_type_code`
- `hardware_status_code`
- `last_online_at`
- `last_maintenance_date`
- `configuration_json`
- `is_active`

Rules:

- Device configuration JSON can hold vendor-specific info.
- Device status must feed alerts/health dashboards.
- Device health should not be embedded only in frontend state.

---

## 9. Orders and operations

### 9.1 `substance`

Product/substance table.

Main fields:

- `substance_id`
- `substance_code`
- `name`
- `chemical_formula`
- `un_number`
- `description`
- `properties_json`
- `is_hazardous`
- `is_active`

### 9.2 `order_header`

Main loading order table.

Main fields:

- `order_id`
- `order_no`
- `sap_order_no`
- `customer_company_id`
- `source_company_id`
- `destination_company_id`
- `forwarder_company_id`
- `substance_id`
- `order_status_code`
- `requested_quantity_kg`
- `planned_load_date`
- `notes`
- `correlation_id`

Recommended additions:

- `sap_sync_status_code`
- `sap_last_synced_at`
- `sap_payload_json`
- `target_quality_code`
- `required_document_json`
- `priority_code`
- `time_window_start`
- `time_window_end`

### 9.3 `order_status_history`

Append-only order status history.

Main fields:

- `order_status_history_id`
- `order_id`
- `status_code`
- `changed_at`
- `changed_by_user_id`
- `note`

### 9.4 `order_operation`

Execution instance/attempt.

Main fields:

- `operation_id`
- `operation_no`
- `order_id`
- `attempt_no`
- `operation_type_code`
- `operation_status_code`
- `site_id`
- `forwarder_company_id`
- `planned_start`
- `planned_end`
- `actual_start`
- `actual_end`
- `rejection_reason_code`
- `rejection_note`
- `is_successful`
- `notes`
- `correlation_id`

Recommended additions:

- `process_variant_code`
- `assigned_driver_id`
- `assigned_tractor_id`
- `assigned_trailer_id`
- `assigned_station_hardware_id`
- `assigned_parking_space_id`
- `execution_snapshot_json`

### 9.5 `operation_status_history`

Append-only operation status history.

---

## 10. Plant visit and steps

### 10.1 `logistic_step`

Configurable steps.

Main fields:

- `logistic_step_id`
- `step_code`
- `name`
- `sequence_no`
- `description`
- `is_active`

### 10.2 `plant_visit`

Main visit table.

Main fields:

- `plant_visit_id`
- `visit_no`
- `operation_id`
- `site_id`
- `driver_id`
- `tractor_id`
- `trailer_id`
- `visit_status_code`
- `entry_gate_hardware_id`
- `exit_gate_hardware_id`
- `entry_at`
- `exit_at`
- `is_completed`
- `correlation_id`
- `notes`

Recommended additions:

- `driver_snapshot_json`
- `tractor_snapshot_json`
- `trailer_snapshot_json`
- `order_snapshot_json`
- `current_instruction_code`
- `blocking_flags_json`
- `exit_reason_code`

### 10.3 `visit_step_execution`

Tracks entered/exited steps.

### 10.4 `visit_authentication`

Tracks gate/terminal/panel authentication attempts.

Main fields:

- `visit_authentication_id`
- `plant_visit_id`
- `auth_medium_id`
- `hardware_object_id`
- `authenticated_at`
- `is_success`
- `failure_reason`
- `notes`

---

## 11. Quality

### 11.1 `quality_analysis`

Main fields:

- `quality_analysis_id`
- `order_id`
- `operation_id`
- `plant_visit_id`
- `analysis_type_code`
- `result_status_code`
- `sample_code`
- `measured_by_user_id`
- `approved_by_user_id`
- `analysis_at`
- `result_json`
- `decision_note`

Recommended additions:

- `attempt_no`
- `is_technical_repeat`
- `is_functional_approval`
- `decision_reason_code`
- `analysis_device_hardware_id`
- `quality_specification_id`

### 11.2 `quality_parameter`

Main fields:

- `quality_parameter_id`
- `code`
- `name`
- `unit_code`
- `min_value`
- `max_value`
- `description`
- `is_active`

### 11.3 `quality_result_detail`

Main fields:

- `quality_result_detail_id`
- `quality_analysis_id`
- `quality_parameter_id`
- `measured_value`
- `text_value`
- `is_pass`
- `measured_at`
- `notes`

Recommended addition:

- `result_status_code`

---

## 12. Certificates, events, alerts, audit, connection logs

### 12.1 `certificate`

Current ERD has certificate table:

- `certificate_id`
- `certificate_no`
- `certificate_type_code`
- `order_id`
- `operation_id`
- `plant_visit_id`
- `issue_date`
- `content_text`
- `issued_by_user_id`

Recommended: keep `certificate`, but add a more generic `document` model for delivery notes/QM documents/print lifecycle.

### 12.2 `event_log`

Operational facts.

Fields include:

- `event_id`
- `event_type_code`
- `event_category_code`
- `severity_code`
- `event_time`
- `source`
- `description`
- `details_json`
- `correlation_id`
- links to site, visit, operation, order, driver, tractor, trailer, hardware, analysis, certificate.

### 12.3 `alert`

Active/resolved alert.

Fields include:

- `alert_id`
- `alert_code`
- `alert_type_code`
- `alert_status_code`
- `severity_code`
- linked event/context
- title/description/responsible team
- raised/acknowledged/resolved fields

### 12.4 `system_message`

Messages shown to users/drivers.

### 12.5 `audit_log`

Sensitive changes.

Fields include:

- `audit_id`
- `entity_name`
- `entity_id`
- `action_name`
- `old_value_json`
- `new_value_json`
- `changed_by_user_id`
- `changed_at`
- `source_ip`
- `user_agent`
- `session_id`
- `correlation_id`
- `note`

### 12.6 `connection_log`

Integration/device connectivity history.

---

## 13. Recommended additional tables

The current ERD is a good base, but these additions are strongly recommended for backend implementation.

### 13.1 `clarification_case`

Purpose: lifecycle for manual exception handling.

Suggested fields:

- `clarification_case_id`
- `case_no`
- `case_type_code`
- `status_code`
- `severity_code`
- `site_id`
- `order_id`
- `operation_id`
- `plant_visit_id`
- `driver_id`
- `tractor_id`
- `trailer_id`
- `hardware_object_id`
- `title`
- `description`
- `blocking_flag`
- `assigned_to_user_id`
- `assigned_role_code`
- `opened_at`
- `opened_by_user_id`
- `resolved_at`
- `resolved_by_user_id`
- `resolution_code`
- `resolution_note`
- `correlation_id`
- timestamps

### 13.2 `parking_space`

Purpose: physical trailer parking slots.

Suggested fields:

- `parking_space_id`
- `site_id`
- `plant_area_id`
- `code`
- `name`
- `status_code` (`free`, `reserved`, `occupied`, `blocked`, `maintenance`)
- `current_trailer_id`
- `reader_hardware_id`
- `is_active`
- timestamps

### 13.3 `parking_event`

Purpose: audit trail of parking assignments/confirmations.

Fields:

- `parking_event_id`
- `parking_space_id`
- `trailer_id`
- `plant_visit_id`
- `operation_id`
- `event_type_code`
- `confirmation_source_code`
- `confirmed_by_user_id`
- `hardware_object_id`
- `occurred_at`
- `notes`
- `correlation_id`

### 13.4 `loading_operation`

Purpose: explicit loading lifecycle, separate from order operation.

Fields:

- `loading_operation_id`
- `order_id`
- `operation_id`
- `plant_visit_id`
- `trailer_id`
- `driver_id`
- `station_hardware_id`
- `status_code`
- `target_quantity_kg`
- `actual_quantity_kg`
- `started_at`
- `completed_at`
- `released_by_user_id`
- `release_source_code`
- `plc_session_id`
- `correlation_id`

### 13.5 `loading_measurement`

Purpose: time-series/measurement records.

Fields:

- `loading_measurement_id`
- `loading_operation_id`
- `measured_at`
- `quantity_kg`
- `pressure_bar`
- `flow_rate`
- `temperature`
- `raw_payload_json`

### 13.6 `quality_specification`

Purpose: product/quality limit definitions.

Fields:

- `quality_specification_id`
- `substance_id`
- `spec_code`
- `name`
- `version_no`
- `valid_from`
- `valid_to`
- `is_active`
- `approved_by_user_id`
- `approved_at`

### 13.7 `quality_specification_parameter`

Purpose: parameter limits per quality specification.

Fields:

- `quality_specification_parameter_id`
- `quality_specification_id`
- `quality_parameter_id`
- `min_value`
- `max_value`
- `unit_code`
- `is_required`

### 13.8 `document`

Purpose: generic document lifecycle.

Fields:

- `document_id`
- `document_no`
- `document_type_code`
- `status_code`
- `order_id`
- `operation_id`
- `plant_visit_id`
- `certificate_id`
- `file_asset_id`
- `generated_at`
- `generated_by_user_id`
- `printed_at`
- `handed_over_at`
- `required_for_exit`
- `correlation_id`

### 13.9 `print_job`

Purpose: print queue and reprint history.

Fields:

- `print_job_id`
- `document_id`
- `printer_hardware_id`
- `status_code`
- `queued_at`
- `started_at`
- `completed_at`
- `failed_at`
- `failure_message`
- `requested_by_user_id`
- `reprint_reason`
- `attempt_no`
- `correlation_id`

### 13.10 `reason_catalog`

Purpose: controlled reason values for block/unblock/cancel/override/reprint/clarification.

Fields:

- `reason_id`
- `reason_code`
- `category_code`
- `label`
- `description`
- `requires_note`
- `is_active`

### 13.11 `integration_message`

Purpose: outbox/inbox for SAP/device/cloud integration.

Fields:

- `integration_message_id`
- `integration_code`
- `direction_code`
- `message_type_code`
- `status_code`
- `payload_json`
- `external_reference`
- `attempt_count`
- `last_attempt_at`
- `next_retry_at`
- `error_message`
- `correlation_id`
- timestamps

### 13.12 `user_table_preference`

Purpose: backend-persisted table column preferences if needed.

Fields:

- `user_table_preference_id`
- `user_id`
- `table_key`
- `visible_columns_json`
- `sort_json`
- `filter_json`

### 13.13 `export_job`

Purpose: async reports/list export.

Fields:

- `export_job_id`
- `user_id`
- `export_type_code`
- `status_code`
- `filters_json`
- `visible_columns_json`
- `file_asset_id`
- `started_at`
- `completed_at`
- `failed_at`
- `error_message`

---

## 14. Migration order recommendation

1. Security: users, roles, permissions.
2. Address/company/site/plant area.
3. File assets.
4. Drivers, tractors, trailers, fleets, auth media.
5. Hardware objects.
6. Substances/products/quality specs.
7. Orders/operations/status histories.
8. Parking tables.
9. Plant visits and step executions.
10. Loading operation and measurements.
11. Quality analysis and details.
12. Documents/print jobs/certificates.
13. Events/alerts/audit/connection logs.
14. Clarification cases.
15. Integration messages.
16. Reporting/export/preferences.
