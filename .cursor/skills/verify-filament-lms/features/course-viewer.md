# Feature: Course Viewer

The course viewer is the in-course interface where learners progress through lessons and steps.

## Sub-features

- **Sidebar navigation**: hierarchical list of lessons and steps
- **Lesson expansion**: click lesson to expand/collapse its steps
- **Step navigation**: click step to view its content
- **Active step indicator**: highlights current step in sidebar
- **Locked steps**: future steps appear disabled until prerequisites complete
- **Step content area**: displays video, text, form, or other material type
- **Completion button**: "Mark as Complete" at bottom of each step
- **Breadcrumb/topbar**: shows current course and lesson context
- **Exit course**: link to return to dashboard

## How to get to it (user POV)

1. From dashboard, click a course card
2. Lands on first incomplete step (or first step if none completed)
3. URL format: `/lms/{course-slug}/{lesson-slug}/{step-slug}`

**Example**: 
- Course: "Introduction to Filament LMS" (`intro-to-filament-lms`)
- Lesson: "Getting Started" (`getting-started`)
- Step: "Welcome" (`getting-started-welcome`)
- Full URL: `http://localhost:8000/lms/intro-to-filament-lms/getting-started/getting-started-welcome`

**Navigate between steps**:
- Click step name in sidebar (if unlocked or completed)
- Or complete current step → auto-advances to next
- Or use browser back/forward

## Driving it with Pest/Playwright

### Pest (Livewire component tests)

Test step page renders correctly:

```php
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Tests\TestUser;

test('step page renders with video player', function () {
    $user = TestUser::create([...]);
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $video = Video::factory()->create();
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => Video::class,
        'material_id' => $video->id,
    ]);
    
    $this->actingAs($user);
    
    $livewire = Livewire::test(StepPage::class, [
        'courseSlug' => $course->slug,
        'lessonSlug' => $lesson->slug,
        'stepSlug' => $step->slug,
    ]);
    
    $livewire->assertSuccessful();
    $livewire->assertSee($step->name);
    $livewire->assertSee('Mark as Complete');
});
```

### Playwright (UI)

Navigate course and verify sidebar:

```javascript
// Enter course
await page.goto('http://localhost:8000/lms');
await page.click('text=Introduction to Filament LMS');

// Wait for step page to load
await page.waitForSelector('text=Welcome', { state: 'visible' });

// Verify sidebar structure
const sidebar = page.locator('[role="navigation"]').first();
await expect(sidebar.locator('text=Getting Started')).toBeVisible();
await expect(sidebar.locator('text=Welcome')).toBeVisible();
await expect(sidebar.locator('text=Installation')).toBeVisible();

// Verify video player renders
await expect(page.locator('iframe[src*="youtube.com"]')).toBeVisible();

// Capture screenshot
await page.screenshot({ path: 'evidence/course-viewer.png', fullPage: true });
```

**Key selectors**:
- Sidebar: typically `aside` or `[role="navigation"]` within Filament panel
- Lesson heading: button or heading with lesson name
- Step link: `a` or `button` with step name, under lesson
- Active step: look for `.fi-active` or similar Filament class
- Locked step: may have `.fi-disabled` or `aria-disabled="true"`
- Video iframe: `iframe[src*="youtube.com"]` or `iframe[src*="vimeo.com"]`
- Completion button: `button:has-text("Mark as Complete")` or `[wire:click*="markComplete"]`

## Gotchas

1. **Sidebar navigation breaks layout**:
   - Filament's sidebar behavior changes based on `topNavigation()` and `sidebarCollapsibleOnDesktop()` config
   - In-course, sidebar replaces primary nav (unless `course_context = 'sub_navigation'` in config)
   - Check `config('filament-lms.navigation.course_context')` for expected behavior

2. **Steps appear unlocked when they shouldn't be**:
   - By default, steps lock until previous steps complete
   - User model can override `canAccessStep()` to allow admins to skip
   - Check if test user has admin role or custom access logic

3. **Video doesn't load or shows 404**:
   - Verify video URL is **embed** format, not watch page:
     - ✅ `https://www.youtube.com/embed/{video-id}`
     - ✅ `https://player.vimeo.com/video/{video-id}`
     - ❌ `https://www.youtube.com/watch?v={video-id}`
   - Check video factory in seed script generates correct format

4. **Clicking sidebar step does nothing**:
   - Step may be locked (prerequisite incomplete)
   - Or Livewire navigation not wired correctly
   - Check browser console for JS errors

5. **Topbar navigation items missing**:
   - Depends on `navigation.mode` config:
     - `mode = 'native'`: shows default "Courses" link
     - `mode = 'mirror_panel'`: copies nav from another panel (e.g., admin panel)
   - If mirroring, verify `mirror_panel_id` is correct

6. **Course completion doesn't trigger**:
   - Completing final step should redirect to `/lms/{course-slug}/completed`
   - Requires ALL non-optional steps completed
   - Check `is_optional` flag on steps if completion doesn't fire

7. **SCORM/embedded player issues**:
   - If course uses embedded player mode (`embedded_player = true`), steps render in iframe
   - SCORM packages need valid `package_path` and `package_launch_path` on Document model

**Preconditions for testing**:
- Course with at least 2 lessons, each with 2+ steps
- Steps linked to material (Video, Document, Link, Test, etc.)
- Test user authenticated and has access to course
- Browser JavaScript enabled (Livewire requires JS)
