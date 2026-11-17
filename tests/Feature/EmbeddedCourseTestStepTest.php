<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Livewire\TestStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Services\CourseEvaluationService;
use Tapp\FilamentLms\Services\ScormProgressService;
use Tapp\FilamentLms\Tests\TestUser;

class EmbeddedCourseLmsUser extends TestUser implements FilamentLmsUserInterface, FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}

class CapturingStepPage extends StepPage
{
    public ?string $redirectedTo = null;

    /** @var list<string> */
    public array $mountedActionNames = [];

    public function redirect($url, $navigate = false)
    {
        $this->redirectedTo = $url;

        return $this;
    }

    public function mountAction(string $name, array $arguments = [], array $context = []): mixed
    {
        $this->mountedActionNames[] = $name;

        return null;
    }
}

/**
 * @return array{
 *     user: EmbeddedCourseLmsUser,
 *     course: Course,
 *     lesson: Lesson,
 *     launchStep: Step,
 *     otherStep: Step,
 *     testStep: Step|null,
 *     form: FilamentForm|null
 * }
 */
function createEmbeddedScormCourseWithTest(string $email, bool $withTest = true): array
{
    $user = EmbeddedCourseLmsUser::query()->create([
        'name' => 'Learner',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'name' => 'SCORM With Test',
        'slug' => 'scorm-with-test-'.Str::slug($email),
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
        'required_test_percentage' => $withTest ? 80 : null,
    ]);

    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'slug' => 'lesson-one',
        'order' => 1,
    ]);

    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);

    $launchStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'name' => 'Course Player',
        'slug' => 'course-player',
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $otherStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'name' => 'Extra Notes',
        'slug' => 'extra-notes',
        'material_type' => null,
        'material_id' => null,
    ]);

    $form = null;
    $testStep = null;

    if ($withTest) {
        $form = FilamentForm::query()->create([
            'name' => 'Knowledge Check Form',
            'slug' => 'knowledge-check-'.Str::slug($email),
        ]);

        FilamentFormField::query()->create([
            'filament_form_id' => $form->id,
            'field' => 'capital',
            'label' => 'Capital of Delaware',
            'type' => 'TEXT',
            'required' => true,
            'order' => 1,
        ]);

        $author = EmbeddedCourseLmsUser::query()->create([
            'name' => 'Author',
            'email' => 'author-'.$email,
            'password' => bcrypt('password'),
        ]);

        $test = Test::query()->create([
            'name' => 'Knowledge Check',
            'filament_form_id' => $form->id,
        ]);

        $rubric = FilamentFormUser::query()->create([
            'filament_form_id' => $form->id,
            'user_id' => $author->id,
            'entry' => [
                ['field' => 'capital', 'type' => 'Text', 'answer' => 'Dover'],
            ],
        ]);

        $test->update(['filament_form_user_id' => $rubric->id]);

        $testStep = Step::factory()->create([
            'lesson_id' => $lesson->id,
            'order' => 2,
            'name' => 'Final Knowledge Check',
            'slug' => 'final-knowledge-check',
            'material_type' => 'test',
            'material_id' => $test->id,
            'retry_step_id' => $launchStep->id,
            'require_perfect_score' => true,
        ]);
    }

    return [
        'user' => $user,
        'course' => $course->fresh(),
        'lesson' => $lesson->fresh(),
        'launchStep' => $launchStep->fresh(),
        'otherStep' => $otherStep->fresh(),
        'testStep' => $testStep?->fresh(),
        'form' => $form,
    ];
}

function embeddedTestEntry(FilamentForm $form, EmbeddedCourseLmsUser $user, string $answer): FilamentFormUser
{
    return FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'capital', 'type' => 'Text', 'answer' => $answer],
        ],
    ]);
}

function mountCapturingStep(Course $course, Lesson $lesson, Step $step, EmbeddedCourseLmsUser $user): CapturingStepPage
{
    test()->actingAs($user);

    $page = new CapturingStepPage;
    $page->mount($course->slug, $lesson->slug, $step->slug);

    return $page;
}

/**
 * @return list<string>
 */
function headerActionLabels(StepPage $page): array
{
    $actions = (new ReflectionMethod($page, 'getHeaderActions'))->invoke($page);

    return array_map(
        fn ($action): string => (string) $action->getLabel(),
        $actions,
    );
}

test('embedded scorm test step opens without redirect and other steps still return to the player', function () {
    $courseData = createEmbeddedScormCourseWithTest('open-test@example.com');
    $user = $courseData['user'];
    $launchStep = $courseData['launchStep'];
    $otherStep = $courseData['otherStep'];
    $testStep = $courseData['testStep'];

    expect($testStep)->not->toBeNull()
        ->and($user->canAccessStep($launchStep))->toBeTrue()
        ->and($user->canAccessStep($testStep))->toBeTrue()
        ->and($user->canAccessStep($otherStep))->toBeFalse()
        ->and($courseData['course']->pendingTestStepFor($user)?->is($testStep))->toBeTrue();

    $page = mountCapturingStep($courseData['course'], $courseData['lesson'], $testStep, $user);

    expect($page->step->is($testStep))->toBeTrue()
        ->and($page->step->material_type)->toBe('test')
        ->and($page->usesEmbeddedPlayerChrome())->toBeFalse()
        ->and($page->shouldRegisterScormBridge())->toBeFalse()
        ->and($page->shouldRegisterHtml5Bridge())->toBeFalse()
        ->and(headerActionLabels($page))->toContain('View All Courses')
        ->and(headerActionLabels($page))->not->toContain('Exit Course')
        ->and(FilamentView::renderHook(PanelsRenderHook::BODY_START)->toHtml())
        ->not->toContain("document.body.classList.add('lms-embedded-player')");

    $testComponent = app(TestStep::class);
    $testComponent->mount($testStep);

    expect($testComponent->test->name)->toBe('Knowledge Check')
        ->and($testComponent->test->form->filamentFormFields->first()?->label)->toBe('Capital of Delaware');

    $otherPage = app(StepPage::class);
    $response = $otherPage->mount($courseData['course']->slug, $courseData['lesson']->slug, $otherStep->slug);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(StepPage::getUrlForStep($launchStep));
});

test('embedded scorm launch step keeps the player chrome', function () {
    $courseData = createEmbeddedScormCourseWithTest('launch-chrome@example.com');

    $page = mountCapturingStep(
        $courseData['course'],
        $courseData['lesson'],
        $courseData['launchStep'],
        $courseData['user'],
    );

    expect($page->usesEmbeddedPlayerChrome())->toBeTrue()
        ->and($page->shouldRegisterScormBridge())->toBeTrue()
        ->and(headerActionLabels($page))->toContain('Exit Course')
        ->and(FilamentView::renderHook(PanelsRenderHook::BODY_START)->toHtml())
        ->toContain("document.body.classList.add('lms-embedded-player')");
});

test('scorm completion redirects to the pending test and a passing score completes the course', function () {
    $courseData = createEmbeddedScormCourseWithTest('pass-test@example.com');
    $user = $courseData['user'];
    $course = $courseData['course'];
    $testStep = $courseData['testStep'];
    $form = $courseData['form'];

    expect($testStep)->not->toBeNull()
        ->and($form)->not->toBeNull();

    $page = mountCapturingStep($course, $courseData['lesson'], $courseData['launchStep'], $user);
    $page->scormCourseComplete();

    expect($page->redirectedTo)->toBe(StepPage::getUrlForStep($testStep))
        ->and($page->mountedActionNames)->toBe([])
        ->and($course->fresh()->completedByUserAt($user->id))->toBeNull()
        ->and((float) $course->fresh()->getCompletionPercentageForUser($user->id))->toBe(50.0)
        ->and(app(ScormProgressService::class)->courseCompletedByUser($course->fresh(), $user))->toBeTrue()
        ->and(app(CourseEvaluationService::class)->courseMeetsCompletionRequirements($course->fresh(), $user->id))->toBeFalse();

    $page->redirectedTo = null;
    $page->exitCourse();

    expect($page->redirectedTo)->toBe(StepPage::getUrlForStep($testStep));

    $testComponent = app(TestStep::class);
    $testComponent->mount($testStep);
    $testComponent->entrySaved(embeddedTestEntry($form, $user, 'Dover'));

    $course->refresh();

    expect($testComponent->testPassed)->toBeTrue()
        ->and($testStep->fresh()->completed_at)->not->toBeNull()
        ->and($course->completedByUserAt($user->id))->not->toBeNull()
        ->and($course->pendingTestStepFor($user))->toBeNull()
        ->and((float) $course->getCompletionPercentageForUser($user->id))->toBe(100.0)
        ->and(app(CourseEvaluationService::class)->courseMeetsCompletionRequirements($course, $user->id))->toBeTrue();

    $testPage = mountCapturingStep($course, $courseData['lesson'], $testStep->fresh(), $user);
    $response = $testPage->complete();

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(CourseCompleted::getUrl([$course->slug]));

    $completed = app(CourseCompleted::class);
    $completedResponse = $completed->mount($course->slug);

    expect($completedResponse)->toBeNull()
        ->and($completed->qualifiedForCertificate)->toBeTrue();

    $html = view('filament-lms::pages.course-completed', [
        'course' => $completed->course,
        'qualifiedForCertificate' => $completed->qualifiedForCertificate,
        'pendingEvaluation' => $completed->pendingEvaluation,
        'evaluationCourse' => $completed->evaluationCourse,
        'evaluationUrl' => $completed->evaluationUrl,
        'overallPercent' => $completed->overallPercent,
        'requiredPercent' => $completed->requiredPercent,
        'testStepDetails' => $completed->testStepDetails,
    ])->render();

    expect($html)->toContain('Congratulations! You have completed "'.$course->name.'"');
});

test('failing an embedded scorm test sends the learner back to the launch step and finishing the player returns them to the test', function () {
    $courseData = createEmbeddedScormCourseWithTest('fail-test@example.com');
    $user = $courseData['user'];
    $course = $courseData['course'];
    $launchStep = $courseData['launchStep'];
    $testStep = $courseData['testStep'];
    $form = $courseData['form'];

    expect($testStep)->not->toBeNull()
        ->and($form)->not->toBeNull();

    app(ScormProgressService::class)->completeAllEligibleSteps($course, $user);

    $testComponent = app(TestStep::class);
    test()->actingAs($user);
    $testComponent->mount($testStep);
    $testComponent->entrySaved(embeddedTestEntry($form, $user, 'Wilmington'));

    expect($testComponent->testPassed)->toBeFalse()
        ->and($testComponent->testCompleted)->toBeFalse()
        ->and($testStep->fresh()->completed_at)->toBeNull()
        ->and($course->fresh()->completedByUserAt($user->id))->toBeNull()
        ->and($course->fresh()->pendingTestStepFor($user)?->is($testStep))->toBeTrue()
        ->and($testStep->retryStep?->is($launchStep))->toBeTrue()
        ->and($testStep->retryStep?->url)->toBe(StepPage::getUrlForStep($launchStep));

    $launchPage = app(StepPage::class);
    $launchResponse = $launchPage->mount($course->slug, $courseData['lesson']->slug, $launchStep->slug);

    expect($launchResponse)->toBeNull()
        ->and($launchPage->usesEmbeddedPlayerChrome())->toBeTrue();

    $page = mountCapturingStep($course, $courseData['lesson'], $launchStep, $user);
    $page->scormCourseComplete();

    expect($page->redirectedTo)->toBe(StepPage::getUrlForStep($testStep));
});

test('an embedded scorm course without a test still exits to the dashboard or completion page', function () {
    $courseData = createEmbeddedScormCourseWithTest('no-test@example.com', withTest: false);
    $user = $courseData['user'];
    $course = $courseData['course'];

    expect($course->pendingTestStepFor($user))->toBeNull();

    $page = mountCapturingStep($course, $courseData['lesson'], $courseData['launchStep'], $user);
    $page->exitCourse();

    expect($page->redirectedTo)->toBe(Dashboard::getUrl())
        ->and($page->mountedActionNames)->toBe([]);

    $page->redirectedTo = null;
    $page->scormCourseComplete();

    expect($page->redirectedTo)->toBeNull()
        ->and($page->mountedActionNames)->toBe([]);

    $page->exitCourse();

    expect($page->redirectedTo)->toBe(CourseCompleted::getUrl([$course->slug]))
        ->and($course->fresh()->completedByUserAt($user->id))->not->toBeNull();
});

test('an embedded scorm course shows the test before the evaluation', function () {
    config(['filament-lms.evaluations.enabled' => true]);

    $courseData = createEmbeddedScormCourseWithTest('evaluation-test@example.com');
    $user = $courseData['user'];
    $course = $courseData['course'];
    $testStep = $courseData['testStep'];
    $form = $courseData['form'];

    expect($testStep)->not->toBeNull()
        ->and($form)->not->toBeNull();

    $evaluationCourse = Course::factory()->create([
        'is_private' => true,
        'slug' => 'scorm-evaluation',
    ]);
    $evaluationLesson = Lesson::factory()->create([
        'course_id' => $evaluationCourse->id,
        'slug' => 'evaluation-lesson',
    ]);
    Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'slug' => 'evaluation-form',
        'material_type' => null,
        'material_id' => null,
    ]);

    $course->update(['evaluation_course_id' => $evaluationCourse->id]);
    $course = $course->fresh();

    $page = mountCapturingStep($course, $courseData['lesson'], $courseData['launchStep'], $user);
    $page->scormCourseComplete();

    expect($page->redirectedTo)->toBe(StepPage::getUrlForStep($testStep))
        ->and($page->mountedActionNames)->toBe([]);

    $testComponent = app(TestStep::class);
    $testComponent->mount($testStep);
    $testComponent->entrySaved(embeddedTestEntry($form, $user, 'Dover'));

    $course->refresh();
    $evaluationUrl = app(CourseEvaluationService::class)->evaluationUrlForPrimaryCourse($course);

    expect($course->completedByUserAt($user->id))->toBeNull()
        ->and($course->pendingTestStepFor($user))->toBeNull()
        ->and(app(CourseEvaluationService::class)->hasPendingEvaluationForUser($course, $user->id))->toBeTrue()
        ->and($evaluationUrl)->not->toBeNull();

    $page = mountCapturingStep($course, $courseData['lesson'], $courseData['launchStep'], $user);
    $page->exitCourse();

    expect($page->redirectedTo)->toBe($evaluationUrl);

    $page->redirectedTo = null;
    $page->scormCourseComplete();

    expect($page->redirectedTo)->toBeNull()
        ->and($page->mountedActionNames)->toBe(['evaluationPrompt']);
});
