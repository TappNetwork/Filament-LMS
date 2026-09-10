<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Services\CourseProgressQueryService;
use Tapp\FilamentLms\Tests\TestTeam;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function (): void {
    config(['filament-lms.user_model' => TestUser::class]);
    config(['filament-lms.tenancy.enabled' => false]);
    Filament::setTenant(null);
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

test('course progress query excludes other tenants when tenancy is enabled', function (): void {
    enableLmsTenancyForTests();

    $teamA = TestTeam::query()->create(['name' => 'Team A']);
    $teamB = TestTeam::query()->create(['name' => 'Team B']);

    $userA = createUserWithCourseProgress('tenant-a@example.com', 'Tenant A Course', $teamA->id);
    createUserWithCourseProgress('tenant-b@example.com', 'Tenant B Course', $teamB->id);

    $admin = TestUser::create([
        'name' => 'Admin',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($admin);
    Filament::setTenant($teamA);

    $rows = CourseProgressQueryService::buildQuery()->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->user_id)->toBe($userA->id)
        ->and($rows->first()->course_name)->toBe('Tenant A Course');

    $userOptions = CourseProgressQueryService::reportUserFilterOptions();

    expect($userOptions)->toHaveKey($userA->id)
        ->and($userOptions)->not->toContain('tenant-b@example.com');
});

test('course progress query is unscoped when tenancy is enabled but no tenant is selected', function (): void {
    enableLmsTenancyForTests();

    $teamA = TestTeam::query()->create(['name' => 'Team A']);
    $teamB = TestTeam::query()->create(['name' => 'Team B']);

    createUserWithCourseProgress('open-a@example.com', 'Open A Course', $teamA->id);
    createUserWithCourseProgress('open-b@example.com', 'Open B Course', $teamB->id);

    $rows = CourseProgressQueryService::buildQuery()->get();

    expect($rows)->toHaveCount(2);
});

function enableLmsTenancyForTests(): void
{
    $schema = Schema::connection((string) config('database.default'));

    if (! $schema->hasTable('teams')) {
        $schema->create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    foreach (['lms_courses', 'lms_lessons', 'lms_steps', 'lms_documents', 'lms_step_user'] as $tableName) {
        if (! $schema->hasColumn($tableName, 'team_id')) {
            $schema->table($tableName, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('team_id')->nullable();
            });
        }
    }

    config([
        'filament-lms.tenancy.enabled' => true,
        'filament-lms.tenancy.model' => TestTeam::class,
        'filament-lms.tenancy.relationship_name' => 'team',
        'filament-lms.tenancy.column' => 'team_id',
    ]);
}

function createUserWithCourseProgress(string $email, string $courseName, int $teamId): TestUser
{
    $user = TestUser::create([
        'name' => $email,
        'first_name' => 'Learner',
        'last_name' => $courseName,
        'email' => $email,
        'password' => bcrypt('password'),
    ]);

    $slug = str_replace(' ', '-', strtolower($courseName));
    $document = Document::create(['name' => $courseName.' Doc', 'file_path' => '/tmp/doc.pdf']);
    $course = Course::factory()->create([
        'name' => $courseName,
        'slug' => $slug,
        'external_id' => $slug,
        'team_id' => $teamId,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $step->complete($user);

    return $user;
}
