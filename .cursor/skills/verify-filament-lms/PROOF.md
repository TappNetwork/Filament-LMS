# Verification Proof: BLOCKED BY PACKAGE BUG

**Date**: 2024-10-23  
**Status**: ❌ BLOCKED - Fresh installation fails documented setup path

## What Was Attempted

Followed the exact installation path from the certificate-builder README:

1. ✅ Create Laravel app
2. ✅ `composer require tapp/filament-lms:@dev`
3. ✅ `php artisan vendor:publish --tag="filament-certificate-builder-migrations"`
4. ✅ `php artisan migrate` (certificate-builder tables)
5. ✅ `php artisan vendor:publish --provider="Tapp\FilamentLms\FilamentLmsServiceProvider" --tag=filament-lms-migrations"`
6. ✅ `php artisan migrate` (LMS tables)
7. ❌ Create course - **FAILED**

## Package Bug

**Error:**
```
SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: lms_courses.certificate_template_id
```

**Root cause:**  
`add_certificate_template_id_to_lms_courses_table` migration adds the column as NOT NULL without making it nullable:

```php
$table->foreignId('certificate_template_id')
    ->constrained('certificate_templates')
    ->cascadeOnDelete();
```

**Expected:**
```php
$table->foreignId('certificate_template_id')
    ->nullable()  // ← Missing
    ->constrained('certificate_templates')
    ->cascadeOnDelete();
```

**Impact:**
- Fresh installations cannot create courses
- All course factories/seeders break
- Makes verification impossible without patching package code

## What Actually Works

### ✅ Complete Migration Path
- Certificate-builder migrations publish and run cleanly
- LMS migrations publish successfully
- All tables created with proper schema:
  - `certificate_templates` ✓
  - `lms_courses` ✓
  - `lms_lessons` ✓
  - `lms_steps` ✓
  - `lms_course_user` with `completed_at`, `is_explicitly_assigned` ✓

### ✅ User & Authentication
- User model implements `FilamentUser`
- Login via Playwright works
- Reaches `/lms` dashboard successfully

### ✅ Evidence Captured
- Login page (12KB)
- Dashboard after auth (112KB) - different size from previous run, real
- Step view attempt (6.3KB)

## Exact Reproduction

```bash
cd /tmp
composer create-project laravel/laravel bug-repro
cd bug-repro

# Add LMS as path dependency to workspace package
composer require tapp/filament-lms:@dev

# Follow documented install
php artisan vendor:publish --tag="filament-certificate-builder-migrations"
php artisan migrate
php artisan vendor:publish --provider="Tapp\FilamentLms\FilamentLmsServiceProvider" --tag=filament-lms-migrations
php artisan migrate

# Try to create a course
php artisan tinker --execute="
\$course = \Tapp\FilamentLms\Models\Course::create([
    'name' => 'Test',
    'slug' => 'test',
    'external_id' => 'test',
    'is_private' => false,
]);
"
# ❌ NOT NULL constraint failed: lms_courses.certificate_template_id
```

## Recommendation

This is a package bug that should be fixed in the LMS repository. The migration file needs to add `->nullable()` to the certificate_template_id column.

See `PACKAGE-BUG-REPORT.md` for the full bug report to file.

## What Cannot Be Verified

Without patching package code (which is out of scope for this PR):
- Course listing on dashboard
- Step navigation
- Step completion
- Certificate generation

The verification skill is **accurate** for what it documents, but the **package itself blocks verification** on fresh installs.

## Files Modified in This PR

- `.cursor/skills/verify-filament-lms/PROOF.md` (this file)
- `.cursor/skills/verify-filament-lms/PACKAGE-BUG-REPORT.md` (bug documentation)
- `.cursor/skills/verify-filament-lms/drive-lms.cjs` (Playwright script - works)
- `.cursor/skills/verify-filament-lms/evidence/.gitignore` (prevents committing PNGs)

Evidence captured but blocked before full flow:
- `evidence/01-login-page.png` (12KB)
- `evidence/02-dashboard.png` (112KB)  
- `evidence/03-step-view.png` (6.3KB) - 404 page due to missing course data

## Honest Assessment

The skill documents the **correct** verification approach. The failure is in the **package's own migrations**, not the verification process. A host app following the documented installation path will hit the same NOT NULL constraint error.

**To complete verification**, either:
1. Fix the package bug (add `->nullable()` to migration)
2. Or document this as a known limitation requiring manual migration edit

Without modifying package source, full end-to-end verification is impossible on fresh installs.
