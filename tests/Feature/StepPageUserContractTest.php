<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Tests\TestUser;

class StepPageContractLmsUser extends TestUser implements FilamentLmsUserInterface {}

/**
 * @return array{0: Course, 1: Lesson, 2: Step}
 */
function createSingleStepCourse(string $slug): array
{
    $course = Course::factory()->create(['slug' => $slug]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'slug' => $slug.'-lesson']);
    $step = Step::factory()->create(['lesson_id' => $lesson->id, 'slug' => $slug.'-step']);

    return [$course, $lesson, $step];
}

test('user model without FilamentLmsUserInterface is not redirected back to the same step', function () {
    // TestUser uses the FilamentLmsUser trait but does not implement the interface,
    // exactly like the User example in the README used to.
    $user = TestUser::create([
        'name' => 'Trait Only User',
        'email' => 'trait-only@example.com',
        'password' => bcrypt('password'),
    ]);
    expect($user)->not->toBeInstanceOf(FilamentLmsUserInterface::class);

    [$course, $lesson, $step] = createSingleStepCourse('contract-course');
    Auth::login($user);

    $page = app(StepPage::class);
    $response = null;
    $exception = null;

    try {
        $response = $page->mount($course->slug, $lesson->slug, $step->slug);
    } catch (LogicException $e) {
        $exception = $e;
    }

    // Previously mount() returned a redirect to linkToCurrentStep(), which for this
    // step is the requested URL itself -> ERR_TOO_MANY_REDIRECTS in the browser.
    if ($response instanceof RedirectResponse) {
        expect($response->getTargetUrl())->not->toBe(StepPage::getUrlForStep($step));
    }

    expect($response)->not->toBeInstanceOf(RedirectResponse::class)
        ->and($exception)->toBeInstanceOf(LogicException::class)
        ->and($exception->getMessage())
        ->toContain(TestUser::class)
        ->toContain(FilamentLmsUserInterface::class);
});

test('user model implementing FilamentLmsUserInterface can mount an accessible step', function () {
    $user = StepPageContractLmsUser::create([
        'name' => 'Interface User',
        'email' => 'interface-user@example.com',
        'password' => bcrypt('password'),
    ]);

    [$course, $lesson, $step] = createSingleStepCourse('contract-ok-course');
    Auth::login($user);

    $page = app(StepPage::class);
    $response = $page->mount($course->slug, $lesson->slug, $step->slug);

    expect($response)->toBeNull()
        ->and($page->step->is($step))->toBeTrue();
});

test('guest visiting a step page is forbidden instead of redirected', function () {
    [$course, $lesson, $step] = createSingleStepCourse('contract-guest-course');

    $page = app(StepPage::class);

    expect(fn () => $page->mount($course->slug, $lesson->slug, $step->slug))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
});
