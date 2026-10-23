# Package Bug Report: NOT NULL constraint on certificate_template_id blocks course creation

## Summary

After following the documented installation path for `tapp/filament-lms` with `tapp/filament-certificate-builder`, fresh course creation fails with:

```
SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: lms_courses.certificate_template_id
```

## Environment

- Laravel: 13.17
- tapp/filament-lms: @dev (commit 87fe65d)
- tapp/filament-certificate-builder: latest
- Database: SQLite

## Reproduction Steps

Following the certificate-builder README and LMS documentation:

```bash
# 1. Create fresh Laravel app
composer create-project laravel/laravel test-app

# 2. Add LMS package
cd test-app
composer require tapp/filament-lms:@dev

# 3. Publish certificate-builder migrations (dependency)
php artisan vendor:publish --tag="filament-certificate-builder-migrations"

# 4. Run migrations
php artisan migrate

# 5. Publish LMS migrations
php artisan vendor:publish --provider="Tapp\FilamentLms\FilamentLmsServiceProvider" --tag=filament-lms-migrations

# 6. Run migrations again
php artisan migrate

# 7. Try to create a course
php artisan tinker
```

In tinker:

```php
use Tapp\FilamentLms\Models\Course;

$course = Course::create([
    'name' => 'Test Course',
    'slug' => 'test-course',
    'external_id' => 'test',
    'is_private' => false,
]);
// ❌ SQLSTATE[23000]: NOT NULL constraint failed: lms_courses.certificate_template_id
```

## Root Cause

The `add_certificate_template_id_to_lms_courses_table` migration adds the column as NOT NULL without a default:

```php
$table->foreignId('certificate_template_id')
    ->constrained('certificate_templates')
    ->cascadeOnDelete();
```

This breaks fresh installations where courses don't have certificates yet.

## Expected Behavior

Certificate assignment should be optional. The column should be nullable:

```php
$table->foreignId('certificate_template_id')
    ->nullable()
    ->constrained('certificate_templates')
    ->cascadeOnDelete();
```

## Impact

- Fresh installations cannot create courses after following documented setup
- All course factories/seeding breaks
- Testing setup fails

## Workaround

Manually modify the published migration before running `php artisan migrate`:

```php
// In database/migrations/*_add_certificate_template_id_to_lms_courses_table.php
$table->foreignId('certificate_template_id')
    ->nullable()  // ← Add this
    ->constrained('certificate_templates')
    ->cascadeOnDelete();
```

## Files Involved

- `database/migrations/add_certificate_template_id_to_lms_courses_table.php.stub`
- `src/Models/Course.php` (may need `$casts` or factory updates)
