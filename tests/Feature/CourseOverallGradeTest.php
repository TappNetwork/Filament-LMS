<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);
});

test('getOverallTestPercentageForUser returns 0 when course has no test steps', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'document',
    ]);

    expect($course->getOverallTestPercentageForUser($user->id))->toBe(0.0);
});

test('getOverallTestPercentageForUser returns average of test step scores', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);

    $form1 = FilamentForm::create(['name' => 'Form 1', 'slug' => 'form-1']);
    FilamentFormField::create([
        'filament_form_id' => $form1->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric1 = FilamentFormUser::create([
        'filament_form_id' => $form1->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    $test1 = Test::create(['name' => 'Test 1', 'filament_form_id' => $form1->id, 'filament_form_user_id' => $rubric1->id]);

    $form2 = FilamentForm::create(['name' => 'Form 2', 'slug' => 'form-2']);
    FilamentFormField::create([
        'filament_form_id' => $form2->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric2 = FilamentFormUser::create([
        'filament_form_id' => $form2->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'B']],
    ]);
    $test2 = Test::create(['name' => 'Test 2', 'filament_form_id' => $form2->id, 'filament_form_user_id' => $rubric2->id]);

    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'test',
        'material_id' => $test1->id,
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 2,
        'material_type' => 'test',
        'material_id' => $test2->id,
    ]);

    FilamentFormUser::create([
        'filament_form_id' => $form1->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    FilamentFormUser::create([
        'filament_form_id' => $form2->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'X']],
    ]);

    $overall = $course->getOverallTestPercentageForUser($user->id);
    expect($overall)->toBe(50.0);
});

test('getFirstTestStepBelowPerfectForUser returns first step below 100 or null', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);

    $form1 = FilamentForm::create(['name' => 'Form 1', 'slug' => 'form-1']);
    FilamentFormField::create([
        'filament_form_id' => $form1->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric1 = FilamentFormUser::create([
        'filament_form_id' => $form1->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    $test1 = Test::create(['name' => 'Test 1', 'filament_form_id' => $form1->id, 'filament_form_user_id' => $rubric1->id]);

    $form2 = FilamentForm::create(['name' => 'Form 2', 'slug' => 'form-2']);
    FilamentFormField::create([
        'filament_form_id' => $form2->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric2 = FilamentFormUser::create([
        'filament_form_id' => $form2->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'B']],
    ]);
    $test2 = Test::create(['name' => 'Test 2', 'filament_form_id' => $form2->id, 'filament_form_user_id' => $rubric2->id]);

    $step1 = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'test',
        'material_id' => $test1->id,
    ]);
    $step2 = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 2,
        'material_type' => 'test',
        'material_id' => $test2->id,
    ]);

    FilamentFormUser::create([
        'filament_form_id' => $form1->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    $firstBelow = $course->getFirstTestStepBelowPerfectForUser($user->id);
    expect($firstBelow)->not->toBeNull();
    expect($firstBelow->id)->toBe($step2->id);

    FilamentFormUser::create([
        'filament_form_id' => $form2->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'Wrong']],
    ]);
    $firstBelow = $course->getFirstTestStepBelowPerfectForUser($user->id);
    expect($firstBelow)->not->toBeNull();
    expect($firstBelow->id)->toBe($step2->id);
});

test('getUrlToFirstTestStepBelowPerfectForUser returns dashboard when all perfect', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $form = FilamentForm::create(['name' => 'Form', 'slug' => 'form']);
    FilamentFormField::create([
        'filament_form_id' => $form->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric = FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    $test = Test::create(['name' => 'Test', 'filament_form_id' => $form->id, 'filament_form_user_id' => $rubric->id]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'test',
        'material_id' => $test->id,
    ]);
    FilamentFormUser::create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);

    $url = $course->getUrlToFirstTestStepBelowPerfectForUser($user->id);
    expect($url)->toBe(Dashboard::getUrl());
});

test('course certificate gate: overall below required sets qualified false', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create(['required_test_percentage' => 80]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);

    $form1 = FilamentForm::create(['name' => 'Form 1', 'slug' => 'form-1']);
    FilamentFormField::create([
        'filament_form_id' => $form1->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric1 = FilamentFormUser::create([
        'filament_form_id' => $form1->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    $test1 = Test::create(['name' => 'Test 1', 'filament_form_id' => $form1->id, 'filament_form_user_id' => $rubric1->id]);
    $form2 = FilamentForm::create(['name' => 'Form 2', 'slug' => 'form-2']);
    FilamentFormField::create([
        'filament_form_id' => $form2->id,
        'field' => 'q1',
        'label' => 'Q1',
        'type' => 'TEXT',
        'required' => true,
        'order' => 1,
    ]);
    $rubric2 = FilamentFormUser::create([
        'filament_form_id' => $form2->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'B']],
    ]);
    $test2 = Test::create(['name' => 'Test 2', 'filament_form_id' => $form2->id, 'filament_form_user_id' => $rubric2->id]);

    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'test',
        'material_id' => $test1->id,
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 2,
        'material_type' => 'test',
        'material_id' => $test2->id,
    ]);

    FilamentFormUser::create([
        'filament_form_id' => $form1->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'A']],
    ]);
    FilamentFormUser::create([
        'filament_form_id' => $form2->id,
        'user_id' => $user->id,
        'entry' => [['field' => 'q1', 'type' => 'Text', 'answer' => 'Wrong']],
    ]);

    $overall = $course->getOverallTestPercentageForUser($user->id);
    expect($overall)->toBe(50.0);
    expect($overall < (float) $course->required_test_percentage)->toBeTrue();
    expect($course->getUrlToFirstTestStepBelowPerfectForUser($user->id))->not->toBe(Dashboard::getUrl());
});

test('course certificate gate: required_test_percentage null implies no percent check', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create(['required_test_percentage' => null]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 1, 'material_type' => 'document']);

    expect($course->required_test_percentage)->toBeNull();
});
