# Feature: Step Completion

Step completion tracks learner progress through courses and determines when a course is finished.

## Sub-features

- **Mark step complete**: button at end of each step
- **Progress persistence**: completion saved to `lms_step_user` pivot table with `completed_at` timestamp
- **Auto-advance**: after marking complete, page advances to next step
- **Revisit completed steps**: learners can return to completed steps (checkmark persists)
- **Course completion trigger**: when all required steps complete, fires `CourseCompleted` event
- **Optional steps**: steps with `is_optional = true` don't block course completion
- **Test step scoring**: test/quiz steps require minimum score (`require_perfect_score` flag)
- **Retry mechanism**: failed test steps can link to a retry step via `retry_step_id`

## How to get to it (user POV)

1. Navigate to any unlocked step in a course
2. Scroll to bottom of step content
3. Click "Mark as Complete" button
4. Page reloads/advances to next step
5. Return to completed step: click step name in sidebar, completion checkmark visible

**Visual indicators**:
- Sidebar: completed steps show checkmark icon
- Dashboard: course card progress bar updates (e.g., "33% Complete")
- Certificate page: unlocked after all steps complete

## Driving it with Pest/Playwright

### Pest (backend logic)

Test step completion flow:

```php
use Tapp\FilamentLms\Events\CourseCompleted;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;
use Illuminate\Support\Facades\Event;

test('step can be marked complete', function () {
    $user = TestUser::create([...]);
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);
    
    expect($step->completedByUser($user))->toBeFalse();
    
    $step->complete($user);
    
    expect($step->fresh()->completedByUser($user))->toBeTrue();
    expect($step->progress->completed_at)->not->toBeNull();
});

test('completing all steps fires CourseCompleted event', function () {
    Event::fake();
    
    $user = TestUser::create([...]);
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step1 = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 1]);
    $step2 = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 2]);
    
    $step1->complete($user);
    Event::assertNotDispatched(CourseCompleted::class);
    
    $step2->complete($user);
    Event::assertDispatched(CourseCompleted::class, function ($event) use ($course, $user) {
        return $event->course->id === $course->id && $event->user->id === $user->id;
    });
});
```

### Playwright (UI)

Complete a step and verify UI updates:

```javascript
// Navigate to first step
await page.goto('http://localhost:8000/lms/intro-to-filament-lms/getting-started/getting-started-welcome');

// Verify completion button exists
await expect(page.locator('button:has-text("Mark as Complete")')).toBeVisible();

// Click complete
await page.click('button:has-text("Mark as Complete")');

// Wait for navigation to next step
await page.waitForURL(/getting-started-installation/);

// Verify previous step shows checkmark in sidebar
const welcomeStep = page.locator('[role="navigation"]').locator('text=Welcome').first();
await expect(welcomeStep.locator('.fi-icon-checkmark')).toBeVisible(); // Adjust selector as needed

// Capture screenshot showing completed state
await page.screenshot({ path: 'evidence/step-completed.png' });
```

**Key selectors**:
- Complete button: `button:has-text("Mark as Complete")` or `[wire:click="markComplete"]` (Livewire)
- Checkmark icon: Filament uses Heroicons, selector like `.heroicon-s-check-circle` or `.fi-icon-checkmark`
- Progress bar: on dashboard, typically Filament progress component `.fi-progress`

## Gotchas

1. **Clicking "Mark as Complete" does nothing**:
   - Check browser console for JS errors (Livewire wire:click not firing)
   - Verify user has `canAccessStep()` permission
   - For test steps, may need minimum score first

2. **Completion doesn't persist after page reload**:
   - Check `lms_step_user` table has record with `completed_at` timestamp
   - Possible issue: database transaction not committed (testbench in-memory DB config)

3. **Course completion doesn't fire**:
   - Verify ALL non-optional steps completed: `$course->steps()->where('is_optional', false)->get()->every(fn($s) => $s->completedByUser($user))`
   - If course has required test percentage: check `required_test_percentage` and actual test scores
   - If course has evaluation: learner must complete evaluation course first

4. **Progress percentage incorrect**:
   - Progress = (completed required steps) / (total required steps)
   - Optional steps don't count toward total
   - Check `is_optional` flag on steps if math seems wrong

5. **Auto-advance goes to wrong step**:
   - Next step determined by `order` column within lesson
   - If order conflicts or gaps exist, may skip steps
   - Verify step order is sequential: 1, 2, 3, ... (no duplicates or jumps)

6. **Certificate doesn't appear after completion**:
   - Course must have `certificate_template_id` set (from certificate-builder package)
   - Or if legacy, `award` field must reference a valid Blade template
   - Check `config/filament-lms.php` for certificate settings

7. **Evaluation blocking completion**:
   - If `evaluation_course_id` set, learner must complete that course first
   - Evaluation course completion unlocks the primary course certificate
   - Check if evaluation course itself is completed

8. **Test step retry logic**:
   - If `retry_step_id` set and test failed, should redirect to retry step
   - Retry step typically a study material (video, document) before retaking test
   - Check `require_perfect_score` flag on test step

**Preconditions for testing**:
- Course with multiple steps to complete
- Test user authenticated and viewing step
- Database persistence enabled (not using array driver)

**Side effects to verify**:
- Database: `lms_step_user` record created/updated with `completed_at`
- Database: `lms_course_user` record updated with `completed_at` when course done
- Event: `CourseCompleted` dispatched (check listeners fire)
- UI: sidebar checkmarks update
- UI: dashboard progress bar updates
