# Feature: Admin Resources

Filament admin panel resources for managing LMS content: Courses, Lessons, Steps, and supporting materials (Videos, Documents, Links, Tests, Images).

## Sub-features

- **CourseResource**: CRUD for courses, assign lessons, manage enrollment, set certificate templates
- **LessonResource**: create/edit lessons within courses, set order
- **StepResource**: create/edit steps within lessons, link materials, set completion rules
- **VideoResource**: manage video URLs (YouTube, Vimeo embeds)
- **DocumentResource**: upload PDFs or SCORM packages
- **LinkResource**: external link steps
- **TestResource**: quiz/survey forms (uses Filament Form Builder)
- **ImageResource**: image material steps
- **CreditCategoryResource**: professional credit categories (if credits enabled)
- **User-Course Relations**: CoursesRelationManager on User resource, AssignCoursesBulkAction
- **User Group Assignment**: criteria-based enrollment (Query Builder rules)

These resources are NOT in the LMS panel (`/lms`). They belong in a separate admin panel (e.g., `/app` or `/admin`) via the `Lms::make()` plugin.

## How to get to it (user POV)

**Setup**: Host app must register Filament admin panel and add LMS plugin:

```php
// app/Providers/Filament/AdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->plugin(\Tapp\FilamentLms\Lms::make());
}
```

**Access**:
1. Navigate to admin panel: `http://localhost:8000/admin`
2. Login with admin credentials
3. Sidebar shows "LMS" navigation group with:
   - Courses
   - Lessons
   - Steps
   - Videos
   - Documents
   - Links
   - Tests
   - Images
   - Credit Categories (if enabled)

**Create a course**:
1. Admin panel → Courses → Create
2. Fill form:
   - Name, Slug, External ID (unique fields)
   - Description (rich text)
   - Is Private (checkbox)
   - Certificate Template (select from certificate-builder templates)
   - Evaluation Course (optional)
3. Save → redirected to course view
4. Add lessons from Lessons relation manager
5. Add steps to lessons via Steps resource

## Driving it with Pest/Playwright

### Pest (Filament resource tests)

Test CourseResource list and create:

```php
use Filament\Facades\Filament;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Tests\TestUser;

test('admin can list courses', function () {
    $admin = TestUser::create([...]);
    $course1 = Course::factory()->create(['name' => 'Course A']);
    $course2 = Course::factory()->create(['name' => 'Course B']);
    
    Filament::setCurrentPanel(Filament::getPanel('admin')); // Or 'lms' depending on where plugin registered
    $this->actingAs($admin);
    
    Livewire::test(CourseResource\Pages\ListCourses::class)
        ->assertCanSeeTableRecords([$course1, $course2])
        ->assertSuccessful();
});

test('admin can create course', function () {
    $admin = TestUser::create([...]);
    $this->actingAs($admin);
    
    Livewire::test(CourseResource\Pages\CreateCourse::class)
        ->fillForm([
            'name' => 'New Course',
            'slug' => 'new-course',
            'external_id' => 'new_course',
            'description' => 'Test description',
            'is_private' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    
    expect(Course::where('name', 'New Course')->exists())->toBeTrue();
});
```

### Playwright (UI)

Navigate admin and create a course:

```javascript
// Navigate to admin panel
await page.goto('http://localhost:8000/admin');

// Login if needed
if (await page.locator('input[name="email"]').isVisible()) {
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
}

// Click "Courses" in sidebar
await page.click('text=Courses');

// Wait for courses list page
await page.waitForSelector('text=Create', { state: 'visible' });

// Click "Create" button
await page.click('text=Create');

// Fill create form
await page.fill('input[name="name"]', 'Test Course via Playwright');
await page.fill('input[name="slug"]', 'test-course-playwright');
await page.fill('input[name="external_id"]', 'test_playwright');
await page.fill('textarea[name="description"]', 'Created by automated test');

// Uncheck "Is Private" if needed
if (await page.locator('input[name="is_private"]:checked').isVisible()) {
  await page.click('label:has-text("Is Private")');
}

// Submit form
await page.click('button:has-text("Create")');

// Wait for redirect to course view
await page.waitForSelector('text=Test Course via Playwright');

// Capture screenshot
await page.screenshot({ path: 'evidence/admin-course-created.png' });
```

**Key selectors**:
- Sidebar nav: Filament uses `.fi-sidebar-item` or similar
- Create button: typically `button:has-text("Create")` or `[href*="/create"]`
- Form fields: standard HTML inputs with `name` attributes
- Submit button: `button[type="submit"]` or `button:has-text("Create")`/`button:has-text("Save")`
- Table: Filament tables use `.filament-tables-table` or similar

## Gotchas

1. **Admin resources not appearing in sidebar**:
   - Verify `Lms::make()` plugin added to admin panel, not LMS panel
   - Check user has permission to access admin panel (`canAccessPanel('admin')`)
   - If using policies, ensure admin user can `viewAny` Course model

2. **Unique constraint errors on create**:
   - Course `name`, `slug`, and `external_id` must be unique
   - If tenancy enabled, uniqueness scoped to tenant
   - Check existing records: `Course::where('slug', 'test-course')->exists()`

3. **Certificate template dropdown empty**:
   - Requires certificate-builder package installed and templates created
   - Templates must match `token_set = 'course'` in `certificate_templates` table
   - If no templates, create one in Certificate Templates resource first

4. **Evaluation course dropdown shows all courses**:
   - Only private courses can be evaluation courses
   - Evaluation course cannot itself have an evaluation (prevents loops)
   - If no options, check courses have `is_private = true`

5. **Lessons/Steps not showing in relation managers**:
   - Relation managers load after course/lesson saved
   - Refresh page if relation manager appears empty
   - Check foreign keys: lesson `course_id`, step `lesson_id`

6. **SCORM package upload fails**:
   - Check `common_cartridge_import.max_upload_size_kb` in config
   - Verify `php.ini` `upload_max_filesize` and `post_max_size` sufficient
   - For large files, enable `multipart_upload.enabled` with Uppy

7. **Import SCORM Package action missing**:
   - Only appears on Courses list page (not create/edit)
   - Requires `storage_disk` configured (defaults to `'local'`)
   - Check disk exists in `config/filesystems.php`

8. **User group assignment tab hidden**:
   - Only shows if `config('filament-lms.user_groups.criteria_provider')` is set
   - Requires UserGroupCriteriaProvider class with Query Builder constraints
   - If missing, feature is disabled

9. **Credit categories not showing**:
   - Check `config('filament-lms.credits_enabled')` is `true`
   - Run migrations: `filament-lms` migrations include `lms_credit_categories` table

10. **Material picker missing types**:
    - Step material can be Video, Document, Link, Test, Image
    - If integrations like Filament Library enabled, additional types appear
    - Check `config('filament-lms.integrations.filament_library.enabled')`

**Preconditions for testing**:
- Admin panel configured with `Lms::make()` plugin
- Admin user with panel access
- Database migrated (LMS and certificate-builder tables)
- Certificate-builder package installed (for certificate template dropdown)

**Side effects to verify**:
- Database: new records in `lms_courses`, `lms_lessons`, `lms_steps`, etc.
- Relationships: `course->lessons`, `lesson->steps` loaded correctly
- Media: uploaded documents/images stored on configured disk
- SCORM: imported packages unzipped and stored in `storage_directory`
