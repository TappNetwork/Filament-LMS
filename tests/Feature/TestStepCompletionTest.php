<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Livewire\Livewire;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Livewire\TestStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Tests\TestUser;

test('test step cannot be completed if not all questions are correct', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    // Create a review step (retry step)
    $reviewStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'name' => 'Review Material',
        'slug' => 'review-material',
        'material_type' => 'document',
    ]);

    // Create a form with 2 questions
    $form = FilamentForm::create([
        'name' => 'Test Form',
        'slug' => 'test-form',
    ]);

    $field1 = FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'question1',
        'label' => 'Question 1',
        'type' => 'Text',
        'required' => true,
        'order' => 1,
    ]);

    $field2 = FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'question2',
        'label' => 'Question 2',
        'type' => 'Text',
        'required' => true,
        'order' => 2,
    ]);

    // Create test with rubric (answer key)
    $test = Test::create([
        'name' => 'Test Quiz',
        'filament_form_id' => $form->id,
    ]);

    // Create rubric (correct answers)
    $rubric = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'question1', 'type' => 'Text', 'answer' => 'Correct Answer 1'],
            ['field' => 'question2', 'type' => 'Text', 'answer' => 'Correct Answer 2'],
        ],
    ]);

    $test->update(['filament_form_user_id' => $rubric->id]);

    // Create test step with retry step
    $testStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 2,
        'name' => 'Test Step',
        'slug' => 'test-step',
        'material_type' => 'test',
        'material_id' => $test->id,
        'retry_step_id' => $reviewStep->id,
    ]);

    // Create user entry with one wrong answer
    $userEntry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'question1', 'type' => 'Text', 'answer' => 'Correct Answer 1'],
            ['field' => 'question2', 'type' => 'Text', 'answer' => 'Wrong Answer'],
        ],
    ]);

    $this->actingAs($user);

    // Mount the TestStep component
    $component = Livewire::test(TestStep::class, ['step' => $testStep]);

    // Simulate entry being saved
    $component->call('entrySaved', $userEntry);

    // Verify test is not passed
    expect($component->get('testPassed'))->toBeFalse();
    expect($component->get('testCompleted'))->toBeFalse();

    // Verify step is not completed
    $testStep->refresh();
    $testStep->load('progress');
    expect($testStep->completed_at)->toBeNull();
});

test('test step can be completed if all questions are correct', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    // Create a form with 2 questions
    $form = FilamentForm::create([
        'name' => 'Test Form',
        'slug' => 'test-form',
    ]);

    $field1 = FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'question1',
        'label' => 'Question 1',
        'type' => 'Text',
        'required' => true,
        'order' => 1,
    ]);

    $field2 = FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'question2',
        'label' => 'Question 2',
        'type' => 'Text',
        'required' => true,
        'order' => 2,
    ]);

    // Create test with rubric (answer key)
    $test = Test::create([
        'name' => 'Test Quiz',
        'filament_form_id' => $form->id,
    ]);

    // Create rubric (correct answers)
    $rubric = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'question1', 'type' => 'Text', 'answer' => 'Correct Answer 1'],
            ['field' => 'question2', 'type' => 'Text', 'answer' => 'Correct Answer 2'],
        ],
    ]);

    $test->update(['filament_form_user_id' => $rubric->id]);

    // Create test step
    $testStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'name' => 'Test Step',
        'slug' => 'test-step',
        'material_type' => 'test',
        'material_id' => $test->id,
    ]);

    // Create user entry with all correct answers
    $userEntry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'question1', 'type' => 'Text', 'answer' => 'Correct Answer 1'],
            ['field' => 'question2', 'type' => 'Text', 'answer' => 'Correct Answer 2'],
        ],
    ]);

    $this->actingAs($user);

    // Mount the TestStep component
    $component = Livewire::test(TestStep::class, ['step' => $testStep]);

    // Simulate entry being saved
    $component->call('entrySaved', $userEntry);

    // Verify test is passed
    expect($component->get('testPassed'))->toBeTrue();
    expect($component->get('testCompleted'))->toBeTrue();

    // Verify step is completed
    $testStep->refresh();
    $testStep->load('progress');
    expect($testStep->completed_at)->not->toBeNull();
});

test('test step shows retry link when test fails and retry step is configured', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    // Create a review step (retry step)
    $reviewStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'name' => 'Review Material',
        'slug' => 'review-material',
        'material_type' => 'document',
    ]);

    // Create a form with 1 question
    $form = FilamentForm::create([
        'name' => 'Test Form',
        'slug' => 'test-form',
    ]);

    $field1 = FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'question1',
        'label' => 'Question 1',
        'type' => 'Text',
        'required' => true,
        'order' => 1,
    ]);

    // Create test with rubric (answer key)
    $test = Test::create([
        'name' => 'Test Quiz',
        'filament_form_id' => $form->id,
    ]);

    // Create rubric (correct answers)
    $rubric = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'question1', 'type' => 'Text', 'answer' => 'Correct Answer'],
        ],
    ]);

    $test->update(['filament_form_user_id' => $rubric->id]);

    // Create test step with retry step
    $testStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 2,
        'name' => 'Test Step',
        'slug' => 'test-step',
        'material_type' => 'test',
        'material_id' => $test->id,
        'retry_step_id' => $reviewStep->id,
    ]);

    // Create user entry with wrong answer
    $userEntry = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'question1', 'type' => 'Text', 'answer' => 'Wrong Answer'],
        ],
    ]);

    $this->actingAs($user);

    // Mount the TestStep component
    $component = Livewire::test(TestStep::class, ['step' => $testStep]);

    // Simulate entry being saved
    $component->call('entrySaved', $userEntry);

    // Verify retry step is available
    expect($component->get('step')->retryStep)->not->toBeNull();
    expect($component->get('step')->retryStep->id)->toBe($reviewStep->id);

    // Verify the view contains the retry link
    $component->assertSee('Review Material and Retry');
});
