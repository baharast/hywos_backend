<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Module Backend Spec — Parking Areas and Trailer Pool

## Source alignment

Parking structure is configured in Company & Plant Configuration V3 and used operationally by Trailer Pool / Car Park and driver instructions.

## Configuration responsibilities

- Parking area code/name.
- Linked plant area/zone.
- Parking spaces with code/label.
- Space type: empty trailer, loaded trailer, service, general.
- Optional trailer chip reader reference.
- Activation/lock through plant configuration lifecycle.

## Operational responsibilities

- Track trailer placement.
- Track trailer pickup.
- Track loaded/empty/service state if available.
- Create event logs for parking actions.
- Provide filtered trailer pool view by parking area/space.

## Suggested tables

- `parking_areas`
- `parking_spaces`
- `trailer_pool_events`
- `trailer_location_states`

## Business rules

- If parking/pickup process variants are used, at least one parking area and space is required before plant configuration activation.
- Parking instructions must reference configured, active spaces.
- Trailer location changes must be event logged.
- Changes to activated parking structure require change request.

## API usage

- Configuration: `/api/plant-configuration/parking-areas`.
- Operations: `/api/trailer-pool` or `/api/operations/trailer-pool`.
