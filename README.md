<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Project Setup — HYWOS.Api (Quickstart)

This section describes how to get the HYWOS.Api backend running locally for development on Windows (PowerShell). It assumes you have PHP >= 8.2, Composer, MySQL and Node.js (optional for frontend assets) installed.

### Prerequisites

- PHP 8.2 or later with common extensions (pdo_mysql, mbstring, openssl, json, tokenizer, xml)
- Composer (https://getcomposer.org)
- MySQL 8.x (or compatible) and a database user
- Node.js + npm (optional, for frontend assets)

### 1) Clone repository

```powershell
git clone <repo-url> HYWOS.Api
cd HYWOS.Api
```

### 2) Install PHP dependencies

```powershell
composer install
```

If `composer.lock` was committed with the repository (it is), `composer install` will install the locked versions.

### 3) Environment file

```powershell
copy .env.example .env
```

Edit `.env` and set database connection values:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hywos_api
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

### 4) Application key

```powershell
php artisan key:generate
```

> Note: If you run `php artisan key:generate` and it fails, check that `.env` exists and is writable and that PHP binary in PATH matches the project requirements.

### 5) Run migrations

```powershell
php artisan migrate
```

### 6) Packages added in this repo

The project has recently added the following packages (see `composer.json`):

- `laravel/sanctum` — API/session/token auth
- `spatie/laravel-permission` — roles & permissions
- `spatie/laravel-activitylog` — activity/audit logging

After `composer install`, publish and migrate the vendor resources:

```powershell
# Spatie permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"

# Spatie activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"

# Sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

php artisan migrate
```

### 7) Optional: Seed roles and permissions

Create a seeder (example `RolePermissionSeeder`) and run:

```powershell
php artisan db:seed --class=RolePermissionSeeder
```

### 8) Run the local server

```powershell
php artisan serve
```

Open http://127.0.0.1:8000

### 9) If you prefer to revert package changes

If those packages were added accidentally and you want to undo the `composer.json`/`composer.lock` changes, you can restore the files from git (only if not pushed or after discussion):

```powershell
git restore composer.json composer.lock
```

Or if you already committed and want to undo the last commit (careful: this resets working tree):

```powershell
git reset --hard HEAD~1
```

### Troubleshooting

- If migrations fail, inspect the SQL error and ensure the DB credentials in `.env` are correct.
- If `php artisan key:generate` fails with exit code, ensure `.env` exists and `APP_KEY` is writable.
- For package-specific publish errors, run `php artisan vendor:publish` without tags to see available providers/tags.

### Next steps (recommended)

- Create initial seeders for roles/permissions and a sample admin user.
- Implement `RolePermissionSeeder` and add it to `DatabaseSeeder` for reproducible dev environments.
- Add documentation in `hywos_backend_laravel_ai_docs/docs/dev/03-package-setup.md` (already added) for package-specific setup notes.
