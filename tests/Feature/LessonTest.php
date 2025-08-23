<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

uses(RefreshDatabase::class);

test('lesson can be created with required fields', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Test Lesson',
        'slug' => 'test-lesson',
        'description' => 'Test lesson description',
    ]);

    expect($lesson->name)->toBe('Test Lesson');
    expect($lesson->slug)->toBe('test-lesson');
    expect($lesson->description)->toBe('Test lesson description');
    expect($lesson->course_id)->toBe($course->id);
});

test('lesson belongs to course', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    expect($lesson->course)->toBeInstanceOf(Course::class);
    expect($lesson->course->id)->toBe($course->id);
});

test('lesson has steps relationship', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    expect($lesson->steps)->toHaveCount(1);
    expect($lesson->steps->first())->toBeInstanceOf(Step::class);
});

test('lesson can get ordered steps', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $step1 = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 2]);
    $step2 = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 1]);

    $orderedSteps = $lesson->steps()->get();

    // Check that the steps are ordered correctly by their order values
    expect($orderedSteps->first()->order)->toBe(1);
    expect($orderedSteps->last()->order)->toBe(2);
});

test('lesson can check if completed by user', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    // Create a test user
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Initially not completed
    expect($lesson->completed_at)->toBeNull();

    // Complete the step
    $step->complete($user);

    // Authenticate the user so the progress relationship can work
    $this->actingAs($user);

    // Refresh the lesson to get updated completion status
    $lesson->refresh();
    $lesson->loadProgress();
    expect($lesson->completed_at)->not->toBeNull();
});
