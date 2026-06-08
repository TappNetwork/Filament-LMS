<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\RelationManagers\CoursesRelationManager;
use Tapp\FilamentLms\Tests\TestUser;

test('courses relation manager is configured for the courses relationship', function (): void {
    $reflection = new ReflectionClass(CoursesRelationManager::class);

    expect(CoursesRelationManager::getRelationshipName())->toBe('courses')
        ->and($reflection->getStaticPropertyValue('title'))->toBe('Assigned Courses')
        ->and($reflection->getStaticPropertyValue('modelLabel'))->toBe('Course');
});

test('user can attach and detach courses via the courses relationship', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    $user->courses()->attach($course->id);

    expect($user->courses()->pluck('lms_courses.id')->all())->toBe([$course->id]);

    $user->courses()->detach($course->id);

    expect($user->courses()->count())->toBe(0);
});

test('attaching the same course twice does not create duplicate pivot rows', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'duplicate@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    $user->courses()->syncWithoutDetaching([$course->id]);
    $user->courses()->syncWithoutDetaching([$course->id]);

    expect(DB::table('lms_course_user')
        ->where('user_id', $user->id)
        ->where('course_id', $course->id)
        ->count())->toBe(1);
});

test('attach query excludes courses already assigned to the user', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'exclude@example.com',
        'password' => bcrypt('password'),
    ]);

    $assignedCourse = Course::factory()->create(['name' => 'Assigned Course']);
    $availableCourse = Course::factory()->create(['name' => 'Available Course']);

    $user->courses()->attach($assignedCourse->id);

    $existingCourseIds = $user->courses()->pluck('course_id');

    $attachableCourseIds = Course::query()
        ->whereNotIn('lms_courses.id', $existingCourseIds)
        ->pluck('id')
        ->all();

    expect($attachableCourseIds)->toContain($availableCourse->id)
        ->and($attachableCourseIds)->not->toContain($assignedCourse->id);
});

test('courses relationship pivot created_at reflects assignment not course creation', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'assigned-at@example.com',
        'password' => bcrypt('password'),
    ]);

    $courseCreatedAt = now()->subDays(30);
    $assignedAt = now()->subDays(2);

    $course = Course::factory()->create();
    $course->forceFill([
        'created_at' => $courseCreatedAt,
        'updated_at' => $courseCreatedAt,
    ])->save();

    $user->courses()->attach($course->id);

    DB::table('lms_course_user')
        ->where('user_id', $user->id)
        ->where('course_id', $course->id)
        ->update([
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ]);

    $attachedCourse = $user->courses()->first();

    expect($attachedCourse->created_at->format('Y-m-d H:i:s'))
        ->toBe($courseCreatedAt->format('Y-m-d H:i:s'))
        ->and($attachedCourse->pivot->created_at->format('Y-m-d H:i:s'))
        ->toBe($assignedAt->format('Y-m-d H:i:s'));
});

test('course search columns can be configured', function (): void {
    config()->set('filament-lms.course_search_columns', ['name']);

    $relationManager = new CoursesRelationManager;
    $method = new ReflectionMethod($relationManager, 'getCourseSearchColumns');

    expect($method->invoke($relationManager))->toBe(['name']);
});
