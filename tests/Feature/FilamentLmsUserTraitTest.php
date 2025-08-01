<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

uses(RefreshDatabase::class);

// Create a test user class that uses the trait
class TestUserWithTrait extends TestUser
{
    // This class already uses the FilamentLmsUser trait from TestUser
}

test('user can access step by default', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    $user = TestUserWithTrait::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Authenticate the user
    $this->actingAs($user);

    // By default, users can access all steps (first step is always available)
    expect($user->canAccessStep($step))->toBe(true);
});

test('user cannot edit step by default', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    $user = TestUserWithTrait::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // By default, users cannot edit steps
    expect($user->canEditStep($step))->toBe(false);
});

test('user can complete course steps', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step1 = Step::factory()->create(['lesson_id' => $lesson->id]);
    $step2 = Step::factory()->create(['lesson_id' => $lesson->id]);

    $user = TestUserWithTrait::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Complete steps
    $step1->complete($user);
    $step2->complete($user);

    // Authenticate the user so the progress relationship can work
    $this->actingAs($user);

    // Check that steps are completed by refreshing and loading progress
    $step1->refresh();
    $step1->load('progress');
    $step2->refresh();
    $step2->load('progress');
    expect($step1->completed_at)->not->toBeNull();
    expect($step2->completed_at)->not->toBeNull();
});
