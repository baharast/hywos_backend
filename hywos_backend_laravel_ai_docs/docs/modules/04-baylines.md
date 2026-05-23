<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Module Backend Spec — Bay Lines / Filling Stations

## Source alignment

Bay lines are now covered by both:

- Company & Plant Configuration V3 for setup and structural data.
- Loading Control V2 for live operational status.

Do not treat bay lines as an isolated CRUD-only module.

## Backend responsibilities

### Configuration side

- Bay line code and name.
- Linked plant area/zone.
- Optional pressure/product hints.
- Related panel/device/PLC references.
- Active/inactive/configured/attention status.
- Locked after plant configuration activation.
- Change request required for structural changes after activation.

### Operational side

- Free/reserved/occupied/loading/fault/maintenance/offline status.
- Current loading operation relation.
- Station health and last heartbeat.
- Related alarms and blockers.
- Last updated timestamp.

## Suggested tables

- `filling_stations` or `bay_lines`
- `station_reservations`
- `device_status_logs`
- `loading_operations`
- `plant_configuration_change_requests`

## API usage

- Configuration view: `/api/plant-configuration/bay-lines`.
- Loading Control station view: `/api/loading-control/stations`.

## Rules

- After plant configuration activation, no direct inline editing.
- Fault/offline/maintenance status must not hide the station.
- Loading Control must show station even when unavailable.
- Related device can be missing during setup as a warning, not always a blocker.
