```markdown
# BayLines (Parking Bay Lines) — Module Spec

This document describes the `BayLine` module: data model, API contract, permissions, and role mappings.

Model
-----
- Table: `baylines`
- Primary key: `id` (UUID string)
- Fields:
  - `code` (string, unique)
  - `name` (string)
  - `site_id` (UUID)
  - `plant_area_id` (UUID)
  - `status_code` (string) — e.g. `free`, `reserved`, `occupied`, `blocked`
  - `current_trailer_id` (UUID)
  - `is_active` (boolean)
  - `created_at`, `updated_at`

API Endpoints
-------------
- `GET /api/baylines` — list (pagination)
- `GET /api/baylines/{id}` — detail
- `POST /api/baylines` — create
- `PUT /api/baylines/{id}` — update
- `DELETE /api/baylines/{id}` — deactivate (soft)

Permissions
-----------
Add the following permission codes to the permission catalog:

- `baylines.view` — view list and details
- `baylines.create` — create new bayline
- `baylines.update` — update bayline
- `baylines.delete` — deactivate bayline

Role mapping (suggested)
------------------------
- `admin`: all permissions (`view`, `create`, `update`, `delete`)
- `dispatcher`: `view`, `create`, `update`
- `operator`: `view`
- `auditor`: `view` (read-only)
- `analysis_specialist`: none by default (not related)

Middleware
----------
- Routes are protected with `auth:sanctum` and `spatie/permission` middleware.
- No custom middleware added yet. If strict site-scoped control is required (e.g. a dispatcher may only manage baylines in their `site_id`), implement `EnsureSiteScope` middleware and apply to create/update routes.

Notes for implementers
---------------------
- Use `BayLineResource` to shape API output and avoid exposing internal fields.
- Use `StoreBayLineRequest` and `UpdateBayLineRequest` to validate input and enforce permission checks.
- Keep business logic (assigning trailers to baylines, reserving) in a domain service (e.g., `ParkingService`) — controllers should stay thin.

Database migration and model are already added in the repo. Remember to seed the permissions and map them to roles.
