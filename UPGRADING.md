# Upgrading

## Award certificates → certificate templates

This is a breaking change. Course certificates are builder-only. The `lms_courses.award` column is removed.

### Before you deploy

1. Require the builder in the host app:

```bash
composer require tapp/filament-certificate-builder
```

2. Keep `filament-lms.awards` in the host config until `php artisan migrate` has run. The upgrade reads those labels (and any `award` values already on courses) to name templates. After migrate succeeds you can delete the `awards` array.

3. Keep published award Blades on disk until migrate has run (`resources/views/vendor/filament-lms/certificates/*.blade.php`). They are the source for logos, copy, borders, and headers.

4. Enable the course token set in `config/certificate-builder.php` and point `filament-lms.integrations.certificate_builder.template_resource` at your Filament resource.

### Upgrade

```bash
php artisan filament-lms:upgrade-awards --dry-run
php artisan migrate
```

`php artisan migrate` runs `drop_award_from_lms_courses_table`, which:

1. Creates or reuses one certificate template per award key
2. Assigns a template to every course that does not already have a living `certificate_template_id` (`award = null` uses **Default Certificate**)
3. Does **not** overwrite hand-edited templates or retarget courses that already point at a different template
4. Fails if any course still lacks a template
5. Makes `certificate_template_id` required (`restrictOnDelete`) and drops `award`

Preview or force-refresh migrated layouts (not custom templates) with:

```bash
php artisan filament-lms:upgrade-awards --dump=storage/logs/lms-award-upgrade.json
php artisan filament-lms:upgrade-awards --force
php artisan filament-lms:upgrade-awards --award=decan
```

`filament-lms:migrate-awards-to-templates` is a hidden alias and will be removed in a later release.

### After migrate

- Delete published LMS award Blades and any Tailwind `@source` aimed only at those views
- Remove `awards` from `config/filament-lms.php`
- Point SCORM imports and new courses at a certificate template (the course form and `CourseFactory` default to **Default Certificate**)
- Training / certification certificates (CHECK `awardCertification()`) are unchanged

### Notes

- Layouts are not pixel-perfect copies of the old Blades
- `--force` only refreshes templates named `{Award label} Certificate`
- Courses with `award` values that had no Blade (for example CHECK `chwad`) get the default Blade source
- Rollback cannot restore per-course `award` values unless you keep the `--dump` JSON
