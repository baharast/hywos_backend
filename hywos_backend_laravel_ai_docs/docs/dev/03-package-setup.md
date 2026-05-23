<!--
HYWOS / FillTrack Backend Laravel AI Docs
Updated package generated from the latest FillTrack Markdown onboarding pack.
Primary backend stack for this package: Laravel + MySQL + REST API for Next.js frontend.
Do not treat older ASP.NET mentions in legacy source material as backend implementation instructions.
-->

# Laravel Package / Project Setup

## Recommended baseline

- Laravel 11 or current approved Laravel version for the team.
- PHP 8.2+ or team-approved runtime.
- MySQL 8.x.
- Redis for queues/cache if available.
- Laravel Sanctum for API auth unless another standard is chosen.
- PHPUnit/Pest for tests.

## Suggested packages

Use only when approved by the team:

- `laravel/sanctum` for API auth.
- `spatie/laravel-permission` for RBAC if the team wants a package instead of custom role tables.
- `spatie/laravel-activitylog` only if it can support required old/new/reason audit behavior cleanly; otherwise implement custom audit logs.
- PDF generation package for certificates/delivery notes when document templates are in scope.
- Queue driver for print/SAP/export jobs.

## Environment variables

```env
APP_NAME=FillTrack
APP_ENV=local
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=file

SAP_ENABLED=false
OPCUA_ENABLED=false
PRINTING_ENABLED=false
```

## Initial artisan steps

```bash
composer create-project laravel/laravel hywos-backend
php artisan migrate
php artisan queue:table
php artisan make:model AuditLog -m
php artisan make:model EventLog -m
php artisan make:controller Api/DriverController --api
php artisan make:request StoreDriverRequest
php artisan make:resource DriverResource
```

## Development data

Create seeders for:

- default roles/permissions,
- admin user,
- plant configuration draft/demo,
- drivers/customers demo data,
- bay lines/parking demo config,
- loading orders demo data,
- station/loading status demo for frontend.

## Documentation discipline

When adding a backend module, update:

- module doc,
- migrations/model relationships,
- API contract notes,
- tests,
- backend status file.
