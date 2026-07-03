<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.evaluations.enabled' => true]);
});

test('primary course completion is deferred until evaluation is completed', function () {
    if (! class_exists(FilamentForm::class)) {
        $this->markTestSkipped('Filament Form Builder is not installed.');
    }

    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'evaluation-test@example.com',
        'password' => bcrypt('password'),
    ]);

    $form = FilamentForm::create([
        'name' => 'Evaluation Form',
        'slug' => 'evaluation-form',
    ]);

    FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'feedback',
        'label' => 'Feedback',
        'type' => 'TEXTAREA',
        'required' => true,
        'order' => 1,
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

    Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'order' => 1,
        'name' => 'Evaluation',
        'slug' => 'evaluation',
        'material_type' => 'form',
        'material_id' => $form->id,
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

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $primaryStep->id,
        'completed_at' => now(),
    ]);

    $primaryCourse->maybeSetCompletedAtForUser($user->id);

    expect($primaryCourse->fresh()->completedByUserAt($user->id))->toBeNull()
        ->and($evaluationCourse->users()->where('user_id', $user->id)->exists())->toBeTrue();

    StepUser::create([
        'user_id' => $user->id,
        'step_id' => $evaluationCourse->steps()->first()->id,
        'completed_at' => now(),
    ]);

    $evaluationCourse->maybeSetCompletedAtForUser($user->id);

    expect($primaryCourse->fresh()->completedByUserAt($user->id))->not->toBeNull()
        ->and($evaluationCourse->fresh()->completedByUserAt($user->id))->not->toBeNull();
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

    Livewire\Livewire::test(\Tapp\FilamentLms\Livewire\FormStep::class, ['step' => $evaluationStep])
        ->assertDontSee('Next');

    $entry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'feedback', 'type' => 'Textarea', 'answer' => 'Great course'],
        ],
    ]);

    Livewire\Livewire::test(\Tapp\FilamentLms\Livewire\FormStep::class, ['step' => $evaluationStep])
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

    Livewire\Livewire::test(\Tapp\FilamentLms\Livewire\FormStep::class, ['step' => $testStep])
        ->assertSet('entry', null);

    $testEntry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'rating', 'type' => 'Radio', 'answer' => 'Disagree'],
        ],
    ]);

    Livewire\Livewire::test(\Tapp\FilamentLms\Livewire\FormStep::class, ['step' => $testStep])
        ->call('entrySaved', $testEntry->id)
        ->assertSet('entry.id', $testEntry->id);

    expect($webdevStep->fresh()->formEntryForUser($user->id)?->id)->toBe($webdevEntry->id)
        ->and($testStep->fresh()->formEntryForUser($user->id)?->id)->toBe($testEntry->id)
        ->and($webdevEntry->fresh()->entry[0]['answer'])->toBe('Agree')
        ->and($testEntry->fresh()->entry[0]['answer'])->toBe('Disagree');
});
