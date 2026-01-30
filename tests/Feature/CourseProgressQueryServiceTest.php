<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Services\CourseProgressQueryService;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function (): void {
    config(['filament-lms.user_model' => TestUser::class]);
});

test('course progress query includes in-progress users who are not yet in lms_course_user', function (): void {
    $user = TestUser::create([
        'name' => 'In Progress User',
        'first_name' => 'In Progress',
        'last_name' => 'User',
        'email' => 'inprogress@example.com',
        'password' => bcrypt('password'),
    ]);

    $document = Document::create(['name' => 'Doc', 'file_path' => '/tmp/doc.pdf']);
    $course = Course::factory()->create([
        'name' => 'Public Course',
        'slug' => 'public-course',
        'external_id' => 'public-course',
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $step1 = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    $step2 = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 2,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $step1->complete($user);

    $rows = CourseProgressQueryService::buildQuery()->get();

    expect($rows)->toHaveCount(1);
    expect((int) $rows->first()->user_id)->toBe($user->id);
    expect((int) $rows->first()->course_id)->toBe($course->id);
    expect($rows->first()->status)->toBe('In Progress');
    expect($rows->first()->completed_at)->toBeNull();
    expect($rows->first()->steps_completed)->toBe(1);
    expect($rows->first()->total_steps)->toBe(2);
});

test('course progress query includes completed users with completed_at from pivot', function (): void {
    $user = TestUser::create([
        'name' => 'Completed User',
        'first_name' => 'Completed',
        'last_name' => 'User',
        'email' => 'completed@example.com',
        'password' => bcrypt('password'),
    ]);

    $document = Document::create(['name' => 'Doc', 'file_path' => '/tmp/doc.pdf']);
    $course = Course::factory()->create([
        'name' => 'Short Course',
        'slug' => 'short-course',
        'external_id' => 'short-course',
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $step->complete($user);

    $rows = CourseProgressQueryService::buildQuery()->get();

    expect($rows)->toHaveCount(1);
    expect((int) $rows->first()->user_id)->toBe($user->id);
    expect((int) $rows->first()->course_id)->toBe($course->id);
    expect($rows->first()->status)->toBe('Completed');
    expect($rows->first()->completed_at)->not->toBeNull();
    expect($rows->first()->steps_completed)->toBe(1);
    expect($rows->first()->total_steps)->toBe(1);
});
