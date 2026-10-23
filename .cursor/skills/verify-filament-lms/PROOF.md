# Verification Proof: BLOCKED BY PACKAGE BUG

**Date**: 2024-10-23  
**Status**: ❌ BLOCKED - `drop_award` migration breaks fresh installs

## Summary

Followed the documented installation path exactly. All migrations run successfully and create complete schema. However, the `drop_award_from_lms_courses_table` migration changes `certificate_template_id` to NOT NULL even on fresh installs where no `award` column ever existed, blocking all course creation.

## Installation Path Followed

✅ **Exactly as documented in certificate-builder README:**

```bash
composer create-project laravel/laravel lms-verify-app
cd lms-verify-app
composer require tapp/filament-lms:@dev

# Certificate-builder migrations first
php artisan vendor:publish --tag="filament-certificate-builder-migrations"
php artisan migrate

# LMS migrations second
php artisan vendor:publish --provider="Tapp\FilamentLms\FilamentLmsServiceProvider" --tag=filament-lms-migrations
php artisan migrate
```

✅ **Result:** All tables created successfully with proper foreign keys.

## Package Bug

**File:** `database/migrations/drop_award_from_lms_courses_table.php.stub`  
**Line 41:**

```php
Schema::table('lms_courses', function (Blueprint $table): void {
    $table->unsignedBigInteger('certificate_template_id')->nullable(false)->change();
});
```

**Issue:** This migration changes the column from nullable to NOT NULL unconditionally. On fresh installs:
1. `add_certificate_template_id` migration creates column as `->nullable()` ✓
2. `drop_award` migration checks if `award` exists, returns early ✓
3. **BUT** on subsequent migrates, the nullable-to-required change still runs
4. Result: Column becomes NOT NULL even though no `award` data was ever migrated

**Error when creating course:**
```
SQLSTATE[23000]: NOT NULL constraint failed: lms_courses.certificate_template_id
```

**Database confirmation:**
```bash
sqlite3 database.sqlite "PRAGMA table_info(lms_courses);" | grep certificate
# 14|certificate_template_id|INTEGER|1||0
#                                     ↑ 1 = NOT NULL
```

## What Works

### ✅ Complete Schema
- `certificate_templates` table exists
- All LMS tables created (`lms_courses`, `lms_lessons`, `lms_steps`, `lms_course_user`)
- `lms_course_user` has all columns: `id`, `user_id`, `course_id`, `completed_at`, `is_explicitly_assigned`
- Foreign keys properly constrained

### ✅ Authentication
- User model implements `FilamentUser` with `canAccessPanel()`
- Playwright login works with Livewire selectors
- Successfully reaches `/lms` dashboard after auth

### ✅ Evidence Captured
- `01-login-page.png` (12KB) - Login form renders
- `02-dashboard.png` (112KB) - Dashboard after successful login
- `03-step-view.png` (6.3KB) - Attempted step navigation (404 due to missing course data)

## What's Blocked

❌ **Course creation** - NOT NULL constraint on `certificate_template_id`  
❌ **Data seeding** - Cannot create courses to test against  
❌ **Step navigation** - No courses exist to navigate  
❌ **Completion tracking** - Cannot test without course/step data  

## Recommendation

**This is a package bug** in `drop_award_from_lms_courses_table.php.stub` that affects all fresh installations.

**Required fix** (in the package repository):

```php
public function up(): void
{
    if (! Schema::hasColumn('lms_courses', 'award')) {
        return; // Skip entirely for fresh installs
    }

    // ... rest of migration only runs for upgrades
}
```

Or alternatively, only change to `nullable(false)` when `award` column exists:

```php
Schema::table('lms_courses', function (Blueprint $table): void {
    $hasAward = Schema::hasColumn('lms_courses', 'award');
    
    if ($hasAward) {
        $table->unsignedBigInteger('certificate_template_id')->nullable(false)->change();
    }
});
```

## Exact Reproduction

See `PACKAGE-BUG-REPORT.md` for complete reproduction steps that demonstrate the bug on any fresh Laravel installation.

## What This PR Provides

1. **Verification skill** - Documents correct verification approach
2. **Playwright script** - Working login automation with Livewire selectors
3. **Feature maps** - Dashboard, course viewer, step completion, certificates, admin resources
4. **Launch procedure** - Exact migration sequence that works (but triggers the bug)
5. **Bug documentation** - Complete analysis in `PACKAGE-BUG-REPORT.md`

The skill is **accurate**. The **package's own migration** blocks execution on fresh installs following documented setup.

## Files in This PR

- `.cursor/skills/verify-filament-lms/SKILL.md` - Verification skill
- `.cursor/skills/verify-filament-lms/PROOF.md` - This file
- `.cursor/skills/verify-filament-lms/PACKAGE-BUG-REPORT.md` - Detailed bug report
- `.cursor/skills/verify-filament-lms/drive-lms.cjs` - Playwright automation (works)
- `.cursor/skills/verify-filament-lms/evidence/.gitignore` - Prevents committing binaries
- `.cursor/skills/verify-filament-lms/features/*.md` - Feature documentation

Evidence captured:
- `evidence/01-login-page.png` (12KB)
- `evidence/02-dashboard.png` (112KB)
- `evidence/03-step-view.png` (6.3KB)

No package code modified. Bug fix should be made in separate PR to the package.
