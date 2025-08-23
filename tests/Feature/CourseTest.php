<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

uses(RefreshDatabase::class);

test('course can be created with required fields', function () {
    $course = Course::factory()->create([
        'name' => 'Test Course',
        'slug' => 'test-course',
        'external_id' => 'test-123',
        'description' => 'Test course description',
    ]);

    expect($course->name)->toBe('Test Course');
    expect($course->slug)->toBe('test-course');
    expect($course->external_id)->toBe('test-123');
    expect($course->description)->toBe('Test course description');
});

test('course has lessons relationship', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    expect($course->lessons)->toHaveCount(1);
    expect($course->lessons->first())->toBeInstanceOf(Lesson::class);
});

test('course can load progress', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    $course->loadProgress();

    expect($course->lessons)->toHaveCount(1);
    expect($course->steps)->toHaveCount(1);
});

test('course can generate link to current step', function () {
    test()->markTestSkipped('Panel context not available in package tests.');
});

test('course can check if completed by user', function () {
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
    expect($course->completedByUserAt($userId))->toBeNull();

    // Complete the step
    $step->complete($user);

    // Now check if course is completed
    $completedAt = $course->completedByUserAt($userId);
    expect($completedAt)->not->toBeNull();
});

test('course can calculate completion percentage', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step1 = Step::factory()->create(['lesson_id' => $lesson->id]);
    $step2 = Step::factory()->create(['lesson_id' => $lesson->id]);

    // Create a test user and authenticate
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $course->loadProgress();
    expect((float) $course->completion_percentage)->toEqual(0.0);

    // Complete one step
    $step1->complete($user);
    $course->refresh();
    $course->loadProgress();
    expect((float) $course->completion_percentage)->toEqual(50.0);

    // Complete the second step
    $step2->complete($user);
    $course->refresh();
    $course->loadProgress();
    expect((float) $course->completion_percentage)->toEqual(100.0);
});

test('course can get steps relationship', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    expect($course->steps)->toHaveCount(1);
    expect($course->steps->first())->toBeInstanceOf(Step::class);
});

test('course can get users relationship', function () {
    $course = Course::factory()->create();

    // Create a test user
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    // Attach user to course
    $course->users()->attach($user->id);

    expect($course->users)->toHaveCount(1);
    expect($course->users->first()->id)->toBe($user->id);
});
