# Feature: Dashboard (Course Library)

The LMS dashboard is the landing page where authenticated learners browse and select courses.

## Sub-features

- **Course grid**: displays all accessible courses as cards
- **Progress indicators**: shows completion percentage on each course card
- **Course filtering**: filter by credit category (if enabled)
- **Course enrollment**: clicking a card enters the course
- **Public vs private courses**: unauthenticated users see only public courses
- **Tenant scoping**: if tenancy enabled, only shows current tenant's courses

## How to get to it (user POV)

1. Navigate to `http://localhost:8000/lms`
2. If not logged in, redirected to `/lms/login`
3. Login with credentials (e.g., `admin@example.com` / `password`)
4. Lands on dashboard at `/lms` (route path is `/` within LMS panel)

**From within a course**:
- Click "Courses" in sidebar navigation
- Or click LMS logo/brand name in top bar
- Returns to dashboard

## Driving it with Pest/Playwright

### Pest (backend logic)

Test that courses are fetched correctly:

```php
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Tests\TestUser;

test('dashboard shows accessible courses', function () {
    $user = TestUser::create([...]);
    $publicCourse = Course::factory()->create(['is_private' => false]);
    $privateCourse = Course::factory()->create(['is_private' => true]);
    
    $this->actingAs($user);
    
    $livewire = Livewire::test(\Tapp\FilamentLms\Pages\Dashboard::class);
    
    // Public course should appear
    $livewire->assertSee($publicCourse->name);
    
    // Private course should NOT appear (user not enrolled)
    $livewire->assertDontSee($privateCourse->name);
});
```

### Playwright (UI)

Navigate and verify dashboard renders:

```javascript
await page.goto('http://localhost:8000/lms');

// Login if needed
if (await page.locator('input[name="email"]').isVisible()) {
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password');
  await page.click('button[type="submit"]');
}

// Wait for dashboard to load
await page.waitForSelector('text=Courses', { state: 'visible' });

// Verify course card appears
await expect(page.locator('text=Introduction to Filament LMS')).toBeVisible();

// Capture screenshot
await page.screenshot({ path: 'evidence/dashboard.png', fullPage: true });
```

**Key selectors**:
- Course cards: typically `.filament-card` or similar Filament panel card class
- Course titles: heading elements (h2/h3) within cards
- Progress indicator: look for progress bar or percentage text on card
- Filter dropdown: `select[name="credit_category_id"]` (if credits enabled)

## Gotchas

1. **No courses appear**: 
   - Check database has courses: `Course::count()`
   - If restrict_course_visibility enabled, user must be enrolled in private courses
   - For public courses, ensure `is_private = false` and course has at least one step

2. **Dashboard page blank or layout broken**:
   - Filament assets may not be compiled. Check if package CSS/JS built correctly.
   - View source and look for 404s on `/assets/` routes

3. **Credit category filter missing**:
   - Only shown if `config('filament-lms.credits_enabled')` is `true`
   - Requires credit categories seeded in database

4. **Progress calculation incorrect**:
   - Progress computed from `lms_step_user` pivot table
   - If user completed steps but progress shows 0%, check `completed_at` timestamp on step_user records

5. **Tenancy issues**:
   - Multi-tenant setup requires tenant context in URL: `/lms/{tenant}/`
   - Non-tenant URLs may 404 or show empty dashboard

6. **Course cards show "Enrolled" badge**:
   - Indicates user has explicit enrollment (`lms_course_user` record with `is_explicitly_assigned = true`)
   - Or matched via user group criteria

**Preconditions for testing**:
- Database migrated and seeded with at least one course
- Test user created and authenticated
- Course has at least one lesson with at least one step (required for course to show)
