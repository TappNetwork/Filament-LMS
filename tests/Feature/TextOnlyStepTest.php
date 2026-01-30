<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

test('step can be created without material', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $step = Step::factory()->withoutMaterial()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Text Only Step',
        'slug' => 'text-only-step',
    ]);

    expect($step->material_id)->toBeNull();
    expect($step->material_type)->toBeNull();
    expect($step->material)->toBeNull();
});

test('text-only step can be completed', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $step = Step::factory()
        ->withoutMaterial()
        ->withText('## Reading Section\n\nThis is a text-only step.')
        ->create(['lesson_id' => $lesson->id]);

    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Initially not completed
    expect($step->completed_at)->toBeNull();

    // Complete the step
    $step->complete($user);

    // Authenticate the user so the progress relationship can work
    $this->actingAs($user);

    // Check if step is now completed
    $step->refresh();
    $step->load('progress');
    expect($step->completed_at)->not->toBeNull();
});

test('step with text but no material has text property', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $textContent = '## Reading Section\n\nTITLE 19 LABOR DELAWARE ADMINISTRATIVE CODE';

    $step = Step::factory()
        ->withoutMaterial()
        ->withText($textContent)
        ->create(['lesson_id' => $lesson->id]);

    expect($step->text)->toBe($textContent);
    expect($step->material_id)->toBeNull();
    expect($step->material_type)->toBeNull();
});

test('step can be created with both text and material', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $step = Step::factory()
        ->withText('This step has both text and a video.')
        ->create([
            'lesson_id' => $lesson->id,
        ]);

    expect($step->text)->not->toBeNull();
    expect($step->material_id)->not->toBeNull();
    expect($step->material_type)->not->toBeNull();
    expect($step->material)->not->toBeNull();
});
