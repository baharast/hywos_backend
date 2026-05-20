# Parkings module

This document describes the `parkings` domain: storage, model, API and permissions.

## Model
- Table: `parkings`
- Primary key: `id` (UUID / char(36))
- Fields: `code`, `name`, `site_id`, `area_id`, `capacity`, `occupied_count`, `status_code`, `current_vehicle_id`, `is_active`, `created_by_user_id`, `updated_by_user_id`, timestamps

Notes: `capacity` and `occupied_count` are integers. `available` is computed by the API as `capacity - occupied_count` (minimum 0).

## API
- Base path: `/api/parkings`

Public (development):
- `GET /api/parkings` — list (supports `per_page`, `site_id`, `area_id`, `is_active` filters)
- `GET /api/parkings/{id}` — get single parking

Manage (development):
- `POST /api/parkings` — create (validated by `StoreParkingRequest`)
- `PUT /api/parkings/{id}` — update (validated by `UpdateParkingRequest`)
- `DELETE /api/parkings/{id}` — deactivate (sets `is_active` to false)

## Permissions
Planned permissions (Spatie): `parkings.view`, `parkings.create`, `parkings.update`, `parkings.delete`.

Note: For development the middleware is currently disabled in `routes/api.php` to allow rapid iteration. Before enabling in production, run the permission seeder and publish/migrate `spatie/permission` tables.

## Seeders and dependencies
- The project includes `SiteSeeder`, `PlantAreaSeeder`, `BayLineSeeder`, `ParkingSeeder`, `RolePermissionSeeder`, and `AdminUserSeeder`.
- `parkings` records require `sites` and `plant_areas` to exist first.
- `ParkingResource` computes `available` as `capacity - occupied_count`.
