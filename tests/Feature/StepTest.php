<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Tests\TestUser;

uses(RefreshDatabase::class);

test('step can be created with required fields', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Test Step',
        'slug' => 'test-step',
        'description' => 'Test step description',
    ]);

    expect($step->name)->toBe('Test Step');
    expect($step->slug)->toBe('test-step');
    expect($step->description)->toBe('Test step description');
    expect($step->lesson_id)->toBe($lesson->id);
});

test('step belongs to lesson', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    expect($step->lesson)->toBeInstanceOf(Lesson::class);
    expect($step->lesson->id)->toBe($lesson->id);
});

test('step can be completed by user', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    // Create a test user
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $userId = $user->id;

    // Initially not completed
    expect($step->completed_at)->toBeNull();

    // Complete the step
    $step->complete($user);

    // Authenticate the user so the progress relationship can work
    $this->actingAs($user);

    // Check if step is now completed by refreshing and loading progress
    $step->refresh();
    $step->load('progress');
    expect($step->completed_at)->not->toBeNull();
});

test('step can check completion status', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    // Create a test user
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $userId = $user->id;

    // Initially not completed
    expect($step->completed_at)->toBeNull();

    // Complete the step
    $step->complete($user);

    // Authenticate the user so the progress relationship can work
    $this->actingAs($user);

    // Check completion status by refreshing and loading progress
    $step->refresh();
    $step->load('progress');
    expect($step->completed_at)->not->toBeNull();
});

test('step can get next step', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step1 = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 1]);
    $step2 = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 2]);

    $nextStep = $step1->next_step;

    expect($nextStep)->toBeInstanceOf(Step::class);
    expect($nextStep->id)->toBe($step2->id);
});

test('step can handle different material types', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $types = ['video', 'document', 'link', 'test'];

    foreach ($types as $type) {
        $step = Step::factory()->create([
            'lesson_id' => $lesson->id,
            'material_type' => $type,
        ]);

        expect($step->material_type)->toBe($type);
    }
});
