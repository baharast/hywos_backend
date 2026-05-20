```markdown
# 03 — Package setup for local development

این سند گام‌های مورد نیاز برای راه‌اندازی پکیج‌هایی که اخیراً به `composer.json` اضافه شده‌اند (Sanctum، Spatie Permission و Spatie Activitylog) را شرح می‌دهد.

## بسته‌های اضافه‌شده
- `laravel/sanctum` — احراز هویت token/session برای SPA و clientهای دستگاه
- `spatie/laravel-permission` — مدیریت roles و permissions
- `spatie/laravel-activitylog` — ثبت لاگ‌های فعالیت و آدیت ساده‌سازی‌شده

## گام‌های نصب محلی
1. نصب وابستگی‌ها:

```powershell
composer install
```

2. ایجاد یا کپی فایل محیط:

```powershell
copy .env.example .env
```

3. مقداردهی متغیرهای دیتابیس در `.env` (DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD)

4. تولید app key:

```powershell
php artisan key:generate
```

5. اجرای مایگریشن‌ها (پس از اطمینان از تنظیمات دیتابیس):

```powershell
php artisan migrate
```

### پیکربندی پکیج‌ها

- Spatie Permission:

```powershell
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --tag="config"
php artisan migrate
```

- Spatie Activitylog:

```powershell
php artisan vendor:publish --provider="Spatie\\Activitylog\\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan vendor:publish --provider="Spatie\\Activitylog\\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

- Laravel Sanctum:

```powershell
php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"
php artisan migrate
```

## تغییرات مورد نیاز در کد
- در مدل `User` trait `HasRoles` (از spatie) را اضافه کنید و تغییرات لازم برای `HasApiTokens` از Sanctum (در صورت نیاز به token API).
- مثال در `app/Models/User.php`:

```php
use Laravel\\Sanctum\\HasApiTokens;
use Spatie\\Permission\\Traits\\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;
}
```

## یکبار اجرا (Seed نمونه)
- برای شروع سریع، یک seeder برای نقش‌های پایه و مجوزها ایجاد و اجرا کنید:

```powershell
php artisan make:seeder RolePermissionSeeder
php artisan db:seed --class=RolePermissionSeeder
```

در seeder نقش‌های MVP (`admin`, `dispatcher`, `operator`, `analysis_specialist`, `auditor`) و مجوزهای پایه را ایجاد کنید.

## نکات و هشدارها
- قبل از انتشار یا merge، بررسی کن که migrations مربوط به spatie و sanctum با طرح DB موجود تداخل نداشته باشند.
- اگر نمی‌خواهی بسته‌ها را فعلاً فعال کنی، بازگرداندن `composer.json`/`composer.lock` راه امنی است.

```