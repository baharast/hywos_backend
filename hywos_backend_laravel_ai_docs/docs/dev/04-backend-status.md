```markdown
# Backend Current Status & Onboarding Brief

Version: 0.1
Date: 2026-05-20

Purpose
-------
This document summarizes the current state of the HYWOS.Api backend repository, lists the exact changes made so far, why they were added, and practical next steps for an AI assistant or a new developer to continue work with minimal ramp-up. Provide this file to any new contributor or AI agent to enable a fast, precise, and safe onboarding.

High-level status
-----------------
- Framework: Laravel (v12)
- PHP: target ^8.2
- Database: MySQL (expected)
- Frontend: Next.js (consumes REST/JSON APIs) — frontend code is out of scope for this repo
- Dependencies: initial composer packages are present; `composer.lock` was updated to match installed packages.

What was added/changed (explicit list)
-------------------------------------
1. Composer / packages
   - `composer.json` updated to include:
     - `laravel/sanctum` — for API/session/token authentication
     - `spatie/laravel-permission` — role & permission management
     - `spatie/laravel-activitylog` — activity / audit logging convenience
   - `composer.lock` updated accordingly (locked versions present). If these packages were added by mistake, revert `composer.json` and `composer.lock`.

2. Documentation
   - `README.md` (root) updated with a Quickstart installation guide and explicit instructions for these new packages (publish, migrate, seed).
   - `hywos_backend_laravel_ai_docs/docs/dev/03-package-setup.md` created with step-by-step local setup instructions for Sanctum and Spatie packages.

3. API response foundations
   - `app/Services/ApiResponse.php` added: centralized service with helpers for all API responses (success, created, noContent, error, validation, notFound, conflict, paginated). Every response includes a `correlation_id` header/body value.
   - `app/Http/Controllers/Api/ApiController.php` added: base API controller that exposes convenient helper methods (`success`, `created`, `error`, `validation`) backed by `ApiResponse`.
   - `hywos_backend_laravel_ai_docs/docs/dev/03-package-setup.md` was extended with a usage example showing how controllers should use the ApiController/ApiResponse.

Why these changes were made
--------------------------
- Standardized responses: to ensure consistent API output shape for the Next.js frontend and for automated testing and logging. Centralizing response shapes simplifies error handling, reduces duplicate code, and ensures `correlation_id` is present for tracing.
- RBAC & Audit packages: `spatie/laravel-permission` and `spatie/laravel-activitylog` are common, well-supported packages that provide out-of-the-box migrations, middleware, traits and helpers to implement roles/permissions and activity logging per the project's AGENTS.md and architecture docs.
- `laravel/sanctum` provides a simple, secure way to support both frontend session-based auth and token-based auth for device/service clients.
- Documentation: the root README and the package-setup doc were updated to reduce on-boarding friction for new developers and to document package-specific setup steps.

Files created or updated (paths)
--------------------------------
- `composer.json` (updated)
- `composer.lock` (updated)
- `README.md` (root) (updated)
- `hywos_backend_laravel_ai_docs/docs/dev/03-package-setup.md` (new)
- `hywos_backend_laravel_ai_docs/docs/dev/04-backend-status.md` (this file)
- `app/Services/ApiResponse.php` (new)
- `app/Http/Controllers/Api/ApiController.php` (new)
 - `app/Models/Customer.php` (new)
 - `database/migrations/2026_05_20_073302_create_customers_table.php` (new)
 - `app/Http/Controllers/Api/CustomerController.php` (new)
 - `app/Http/Resources/CustomerResource.php` (new)
 - `app/Http/Requests/StoreCustomerRequest.php` (new)
 - `app/Http/Requests/UpdateCustomerRequest.php` (new)
 - `database/seeders/CustomerSeeder.php` (new)
 - `app/Models/Site.php` (new)
 - `app/Models/PlantArea.php` (new)
 - `database/migrations/2026_05_20_073300_create_sites_table.php` (new)
 - `database/migrations/2026_05_20_073301_create_plant_areas_table.php` (new)
 - `database/seeders/RolePermissionSeeder.php` (new)
 - `database/seeders/SiteSeeder.php` (new)
 - `database/seeders/PlantAreaSeeder.php` (new)
 - `database/seeders/AdminUserSeeder.php` (new)
 - `database/seeders/BayLineSeeder.php` (new)
 - `database/seeders/ParkingSeeder.php` (new)

Quick reproducible setup (commands)
-----------------------------------
Run these steps on a fresh clone to reproduce the environment described here. Use PowerShell on Windows.

```powershell
cd HYWOS.Api
composer install
copy .env.example .env
# Edit .env with DB credentials
php artisan key:generate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

If you want to revert the composer package changes instead of installing them:

```powershell
git restore composer.json composer.lock
```

Important conventions and patterns to follow
-----------------------------------------
- Controllers must remain thin. Put business logic into Actions/Services under `app/Services` or `app/Actions`.
- Use Form Requests for validation and API Resources for output shaping.
- Use `ApiController` / `ApiResponse` for all responses to maintain a uniform response shape and correlation id.
- Use UUIDs for business entity IDs where appropriate; the docs recommend `char(36)` primary keys.
- Never expose sensitive fields like `national_id_hash` via API Resources.

Suggested next work items (priority order)
----------------------------------------
1. Add `HasApiTokens` to `app/Models/User.php` if Sanctum token-based auth is required.
3. Implement a small `CorrelationId` middleware to normalize/header-set correlation id for all requests and responses.
4. Add `GET /api/health` endpoint and a minimal global error handler that uses `ApiResponse::error`.
5. Implement basic auth endpoints (`/api/auth/login`, `/api/auth/logout`, `/api/auth/me`) using Sanctum.

Notes about activation endpoints
--------------------------------
- For several entities (BayLine, Parking, Customer) activation is managed via dedicated endpoints (`PATCH /api/{resource}/{id}/activate` and `PATCH /api/{resource}/{id}/deactivate`) rather than through the generic `update` request. `Update*Request` classes do not include `is_active` to prevent accidental activation changes during updates.

Notes for AI agents / new developers
----------------------------------
- Start by reading `hywos_backend_laravel_ai_docs/AGENTS.md` and the `docs/` directory — these are the source-of-truth for business rules.
- Follow the Milestone roadmap in `hywos_backend_laravel_ai_docs/docs/dev/01-backend-implementation-roadmap.md`.
- Make small, reviewable commits. For any domain or protocol uncertainty, create an issue or mark open questions in the code/comments rather than guessing.
- Tests: add feature tests for auth flow, RBAC checks, and ApiResponse payload shapes early — they catch integration issues in package configuration.

Contact / Ownership
-------------------
If unsure about domain decisions or package usage, escalate to the project technical lead or open an RFC issue in the repository. Preserve auditability for any manual override of business rules.

End of file
```
