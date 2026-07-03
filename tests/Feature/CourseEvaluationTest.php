<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Events\CourseCompleted as CourseCompletedEvent;
use Tapp\FilamentLms\Livewire\FormStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Services\CourseEvaluationService;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.evaluations.enabled' => true]);
});

test('evaluation link validation rejects public courses and backwards configuration', function () {
    $trainingCourse = Course::factory()->create(['is_private' => false]);
    $evaluationCourse = Course::factory()->create(['is_private' => true]);

    $service = app(CourseEvaluationService::class);

    expect($service->canLinkEvaluationCourse($trainingCourse, $evaluationCourse))->toBeTrue()
        ->and($service->canLinkEvaluationCourse($evaluationCourse, $trainingCourse))->toBeFalse()
        ->and($service->canLinkEvaluationCourse($trainingCourse, $trainingCourse))->toBeFalse();
});

test('canSelectEvaluationCourse validates evaluation targets on create', function () {
    $service = app(CourseEvaluationService::class);

    $validTarget = Course::factory()->create(['is_private' => true]);
    $publicCourse = Course::factory()->create(['is_private' => false]);
    $nestedEvaluation = Course::factory()->create([
        'is_private' => true,
        'evaluation_course_id' => Course::factory()->create(['is_private' => true])->id,
    ]);

    expect($service->canSelectEvaluationCourse(null))->toBeTrue()
        ->and($service->canSelectEvaluationCourse($validTarget))->toBeTrue()
        ->and($service->canSelectEvaluationCourse($publicCourse))->toBeFalse()
        ->and($service->canSelectEvaluationCourse($nestedEvaluation))->toBeFalse();
});

test('primary course completion is deferred until evaluation is completed', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'evaluation-test@example.com',
        'password' => bcrypt('password'),
    ]);

    $evaluationCourse = Course::factory()->create([
        'name' => 'Training Evaluation',
        'slug' => 'training-evaluation',
        'external_id' => 'training_evaluation',
        'is_private' => true,
    ]);

    $evaluationLesson = Lesson::factory()->create([
        'course_id' => $evaluationCourse->id,
        'order' => 1,
    ]);

    $evaluationStep = Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'order' => 1,
        'name' => 'Evaluation',
        'slug' => 'evaluation',
    ]);

    $primaryCourse = Course::factory()->create([
        'name' => 'Primary Training',
        'slug' => 'primary-training',
        'external_id' => 'primary_training',
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => true,
    ]);

    $primaryCourse->users()->attach($user->id);

    $primaryLesson = Lesson::factory()->create([
        'course_id' => $primaryCourse->id,
        'order' => 1,
    ]);

    $primaryStep = Step::factory()->create([
        'lesson_id' => $primaryLesson->id,
        'order' => 1,
        'name' => 'Lesson Step',
        'slug' => 'lesson-step',
    ]);

    Event::fake([CourseCompletedEvent::class]);

    $primaryStep->complete($user);

    Event::assertNotDispatched(CourseCompletedEvent::class, function (CourseCompletedEvent $event) use ($primaryCourse): bool {
        return $event->course->is($primaryCourse);
    });

    expect($primaryCourse->fresh()->completedByUserAt($user->id))->toBeNull()
        ->and($evaluationCourse->users()->where('user_id', $user->id)->exists())->toBeTrue();

    $evaluationStep->complete($user);

    Event::assertDispatched(CourseCompletedEvent::class, function (CourseCompletedEvent $event) use ($user, $primaryCourse): bool {
        return $event->user->is($user) && $event->course->is($primaryCourse);
    });

    expect($primaryCourse->fresh()->completedByUserAt($user->id))->not->toBeNull()
        ->and($evaluationCourse->fresh()->completedByUserAt($user->id))->not->toBeNull();
});

test('completed page for evaluation course redirects to primary course certificate page', function () {
    $user = TestUser::create([
        'name' => 'Evaluation Certificate User',
        'email' => 'evaluation-certificate@example.com',
        'password' => bcrypt('password'),
    ]);

    $evaluationCourse = Course::factory()->create([
        'name' => 'Certificate Evaluation',
        'slug' => 'certificate-evaluation',
        'external_id' => 'certificate_evaluation',
        'is_private' => true,
    ]);
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    $evaluationStep = Step::factory()->create(['lesson_id' => $evaluationLesson->id]);

    $primaryCourse = Course::factory()->create([
        'name' => 'Certificate Training',
        'slug' => 'certificate-training',
        'external_id' => 'certificate_training',
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => true,
    ]);
    $primaryCourse->users()->attach($user->id);

    $primaryLesson = Lesson::factory()->create(['course_id' => $primaryCourse->id]);
    $primaryStep = Step::factory()->create(['lesson_id' => $primaryLesson->id]);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    $primaryCourse->maybeSetCompletedAtForUser($user->id);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $evaluationStep->id,
        'completed_at' => now(),
    ]);

    $evaluationCourse->maybeSetCompletedAtForUser($user->id);

    $this->actingAs($user);

    $response = app(CourseCompleted::class)->mount($evaluationCourse->slug);

    expect($response)
        ->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getTargetUrl())->toBe(CourseCompleted::getUrl([$primaryCourse->slug]));
});

test('evaluation course is never shown on the dashboard', function () {
    $user = TestUser::create([
        'name' => 'Dashboard User',
        'email' => 'dashboard-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $evaluationCourse = Course::factory()->create([
        'name' => 'Hidden Evaluation',
        'slug' => 'hidden-evaluation',
        'external_id' => 'hidden_evaluation',
        'is_private' => true,
    ]);

    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    Step::factory()->create(['lesson_id' => $evaluationLesson->id]);

    $primaryCourse = Course::factory()->create([
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => true,
    ]);

    $primaryCourse->users()->attach($user->id);
    $evaluationCourse->users()->attach($user->id);

    $primaryLesson = Lesson::factory()->create(['course_id' => $primaryCourse->id]);
    $primaryStep = Step::factory()->create(['lesson_id' => $primaryLesson->id]);

    expect(Course::accessibleTo($user)->pluck('id'))->not->toContain($evaluationCourse->id);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    expect(Course::accessibleTo($user)->pluck('id'))->not->toContain($evaluationCourse->id)
        ->and(Course::accessibleTo($user)->pluck('id'))->toContain($primaryCourse->id);
});

test('public primary course unlocks evaluation without enrollment on the training course', function () {
    $user = TestUser::create([
        'name' => 'Public Primary User',
        'email' => 'public-primary-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $evaluationCourse = Course::factory()->create(['is_private' => true]);
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    $evaluationStep = Step::factory()->create(['lesson_id' => $evaluationLesson->id]);

    $primaryCourse = Course::factory()->create([
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => false,
    ]);

    $primaryLesson = Lesson::factory()->create(['course_id' => $primaryCourse->id]);
    $primaryStep = Step::factory()->create(['lesson_id' => $primaryLesson->id]);

    expect($primaryCourse->users()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->canAccessStep($evaluationStep))->toBeFalse();

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    $primaryCourse->maybeSetCompletedAtForUser($user->id);

    expect($user->canAccessStep($evaluationStep))->toBeTrue()
        ->and($evaluationCourse->users()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(Course::accessibleTo($user)->pluck('id'))->not->toContain($evaluationCourse->id);
});

test('user cannot access evaluation step before primary course is finished', function () {
    $user = TestUser::create([
        'name' => 'Locked User',
        'email' => 'locked-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $evaluationCourse = Course::factory()->create(['is_private' => true]);
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    $evaluationStep = Step::factory()->create(['lesson_id' => $evaluationLesson->id]);

    $primaryCourse = Course::factory()->create([
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => true,
    ]);

    $primaryCourse->users()->attach($user->id);
    $evaluationCourse->users()->attach($user->id);

    expect($user->canAccessStep($evaluationStep))->toBeFalse();

    $primaryLesson = Lesson::factory()->create(['course_id' => $primaryCourse->id]);
    $primaryStep = Step::factory()->create(['lesson_id' => $primaryLesson->id]);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    expect($user->canAccessStep($evaluationStep))->toBeTrue();
});

test('required test percentage must be met before evaluation is unlocked', function () {
    $user = TestUser::create([
        'name' => 'Failed Test User',
        'email' => 'failed-test-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $evaluationCourse = Course::factory()->create(['is_private' => true]);
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    $evaluationStep = Step::factory()->create(['lesson_id' => $evaluationLesson->id]);

    $primaryCourse = Course::factory()->create([
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => true,
        'required_test_percentage' => 80,
    ]);

    $primaryCourse->users()->attach($user->id);

    $primaryLesson = Lesson::factory()->create(['course_id' => $primaryCourse->id]);
    $primaryStep = Step::factory()->create([
        'lesson_id' => $primaryLesson->id,
        'material_type' => 'test',
        'material_id' => null,
    ]);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    $service = app(CourseEvaluationService::class);

    expect($primaryCourse->allStepsCompletedByUser($user->id))->toBeTrue()
        ->and($primaryCourse->getOverallTestPercentageForUser($user->id))->toBe(0.0)
        ->and($service->hasPendingEvaluationForUser($primaryCourse, $user->id))->toBeFalse()
        ->and($user->canAccessStep($evaluationStep))->toBeFalse();

    $primaryCourse->ensureEvaluationAssigned($user->id);

    expect($evaluationCourse->users()->where('user_id', $user->id)->exists())->toBeFalse();

    $this->actingAs($user);

    $page = app(CourseCompleted::class);
    $page->mount($primaryCourse->slug);

    expect($page->pendingEvaluation)->toBeFalse()
        ->and($page->qualifiedForCertificate)->toBeFalse()
        ->and($page->overallPercent)->toBe(0.0)
        ->and($page->requiredPercent)->toBe(80);
});

test('evaluation form step hides next button and advances after submit', function () {
    if (! class_exists(FilamentForm::class)) {
        $this->markTestSkipped('Filament Form Builder is not installed.');
    }

    $user = TestUser::create([
        'name' => 'Form Next User',
        'email' => 'form-next-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $form = FilamentForm::create([
        'name' => 'Evaluation Form Next',
        'slug' => 'form-evaluation-next',
    ]);

    $evaluationCourse = Course::factory()->create(['is_private' => true]);
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    $evaluationStep = Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);

    Course::factory()->create([
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(FormStep::class, ['step' => $evaluationStep])
        ->assertDontSee('Next');

    $entry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'feedback', 'type' => 'Textarea', 'answer' => 'Great course'],
        ],
    ]);

    Livewire::test(FormStep::class, ['step' => $evaluationStep])
        ->call('entrySaved', $entry)
        ->assertDispatched('complete-step');
});

test('primary course without evaluation has no evaluation submission url', function () {
    $primaryCourse = Course::factory()->create();

    expect($primaryCourse->evaluationSubmissionUrl())->toBeNull();
});

test('completing evaluation form step marks evaluation course complete and finalizes primary course', function () {
    if (! class_exists(FilamentForm::class)) {
        $this->markTestSkipped('Filament Form Builder is not installed.');
    }

    $user = TestUser::create([
        'name' => 'Form User',
        'email' => 'form-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $form = FilamentForm::create([
        'name' => 'Evaluation Form',
        'slug' => 'form-evaluation',
    ]);

    FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'feedback',
        'label' => 'Feedback',
        'type' => 'TEXTAREA',
        'required' => true,
        'order' => 1,
    ]);

    $evaluationCourse = Course::factory()->create(['is_private' => true]);
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id]);
    $evaluationStep = Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);

    $primaryCourse = Course::factory()->create([
        'evaluation_course_id' => $evaluationCourse->id,
        'is_private' => true,
    ]);

    $primaryCourse->users()->attach($user->id);

    $primaryLesson = Lesson::factory()->create(['course_id' => $primaryCourse->id]);
    $primaryStep = Step::factory()->create(['lesson_id' => $primaryLesson->id]);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    $primaryCourse->maybeSetCompletedAtForUser($user->id);

    $this->actingAs($user);

    FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'feedback', 'type' => 'Textarea', 'answer' => 'Great course'],
        ],
    ]);

    $evaluationStep->complete($user);

    expect($primaryCourse->fresh()->completedByUserAt($user->id))->not->toBeNull()
        ->and($evaluationCourse->fresh()->completedByUserAt($user->id))->not->toBeNull();
});

test('form steps scope submissions per step when the same form is reused', function () {
    if (! class_exists(FilamentForm::class)) {
        $this->markTestSkipped('Filament Form Builder is not installed.');
    }

    $user = TestUser::create([
        'name' => 'Shared Form User',
        'email' => 'shared-form-evaluation@example.com',
        'password' => bcrypt('password'),
    ]);

    $form = FilamentForm::create([
        'name' => 'Shared Evaluation Form',
        'slug' => 'shared-evaluation-form',
    ]);

    $webdevEvaluation = Course::factory()->create(['is_private' => true, 'name' => 'Webdev Evaluation']);
    $webdevLesson = Lesson::factory()->create(['course_id' => $webdevEvaluation->id]);
    $webdevStep = Step::factory()->create([
        'lesson_id' => $webdevLesson->id,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);

    $testEvaluation = Course::factory()->create(['is_private' => true, 'name' => 'Test Evaluation']);
    $testLesson = Lesson::factory()->create(['course_id' => $testEvaluation->id]);
    $testStep = Step::factory()->create([
        'lesson_id' => $testLesson->id,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);

    $webdevEntry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'rating', 'type' => 'Radio', 'answer' => 'Agree'],
        ],
    ]);

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $webdevStep->id,
        'completed_at' => now()->subHour(),
        'filament_form_user_id' => $webdevEntry->id,
    ]);

    $this->actingAs($user);

    Livewire::test(FormStep::class, ['step' => $testStep])
        ->assertSet('entry', null);

    $testEntry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'rating', 'type' => 'Radio', 'answer' => 'Disagree'],
        ],
    ]);

    Livewire::test(FormStep::class, ['step' => $testStep])
        ->call('entrySaved', $testEntry->id)
        ->assertSet('entry.id', $testEntry->id);

    expect($webdevStep->fresh()->formEntryForUser($user->id)?->id)->toBe($webdevEntry->id)
        ->and($testStep->fresh()->formEntryForUser($user->id)?->id)->toBe($testEntry->id)
        ->and($webdevEntry->fresh()->entry[0]['answer'])->toBe('Agree')
        ->and($testEntry->fresh()->entry[0]['answer'])->toBe('Disagree');
});
