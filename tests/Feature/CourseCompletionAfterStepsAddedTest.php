<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);
});

function documentStep(Lesson $lesson, int $order): Step
{
    $document = Document::create(['name' => "Doc {$order}", 'file_path' => '/tmp/test.pdf']);

    return Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => $order,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
}

function learner(string $email): TestUser
{
    return TestUser::create(['name' => 'Learner', 'email' => $email, 'password' => bcrypt('password')]);
}

function pivotRow(Course $course, TestUser $user): ?object
{
    return DB::table('lms_course_user')->where('course_id', $course->id)->where('user_id', $user->id)->first();
}

test('completion page stays available after steps are added to a completed course', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = documentStep($lesson, 1);
    $user = learner('done@example.com');

    $step->complete($user);
    expect($course->completedByUserAt($user->id))->not->toBeNull();

    documentStep($lesson, 2);

    expect($course->allStepsCompletedByUser($user->id))->toBeFalse()
        ->and($course->completedByUserAt($user->id))->not->toBeNull()
        ->and($course->completionPageAvailableForUser($user->id))->toBeTrue();
});

test('completion page is unavailable to a learner who has not completed the course', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $first = documentStep($lesson, 1);
    documentStep($lesson, 2);
    $user = learner('partial@example.com');

    $first->complete($user);

    expect($course->completionPageAvailableForUser($user->id))->toBeFalse();
});

test('unassigning users keeps completed rows and removes the rest', function () {
    $course = Course::factory()->create(['is_private' => true]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = documentStep($lesson, 1);
    $completed = learner('completed@example.com');
    $assignedOnly = learner('assigned@example.com');

    $course->users()->attach($assignedOnly->id, ['is_explicitly_assigned' => true]);
    $course->users()->attach($completed->id, ['is_explicitly_assigned' => true]);
    $step->complete($completed);
    $completedAt = $course->completedByUserAt($completed->id);

    $course->unassignUsers([$completed->id, $assignedOnly->id]);

    expect(pivotRow($course, $assignedOnly))->toBeNull();

    $row = pivotRow($course, $completed);
    expect($row)->not->toBeNull()
        ->and((int) $row->is_explicitly_assigned)->toBe(0)
        ->and($course->completedByUserAt($completed->id))->toBe($completedAt)
        ->and($course->canBeAccessedBy($completed))->toBeFalse();
});

test('backfill restores completion for steps that existed when the learner finished', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    Carbon::setTestNow('2026-03-01 10:00:00');
    $original = documentStep($lesson, 1);
    $finisher = learner('finisher@example.com');
    $starter = learner('starter@example.com');

    Carbon::setTestNow('2026-03-07 12:38:29');
    $original->complete($finisher);

    // Simulate the completion row having been detached later.
    DB::table('lms_course_user')->where('course_id', $course->id)->delete();

    Carbon::setTestNow('2026-09-25 00:24:00');
    $added = documentStep($lesson, 2);

    Carbon::setTestNow('2026-10-01 09:00:00');
    $added->complete($starter);
    Carbon::setTestNow();

    $this->artisan('filament-lms:backfill-course-completed-at', ['--course' => [$course->id]])->assertSuccessful();
    expect(pivotRow($course, $finisher))->toBeNull();

    $this->artisan('filament-lms:backfill-course-completed-at', [
        '--course' => [$course->id],
        '--steps-existing-at-completion' => true,
        '--dry-run' => true,
    ])->assertSuccessful();
    expect(pivotRow($course, $finisher))->toBeNull();

    $this->artisan('filament-lms:backfill-course-completed-at', [
        '--course' => [$course->id],
        '--steps-existing-at-completion' => true,
    ])->assertSuccessful();

    $row = pivotRow($course, $finisher);
    expect($row)->not->toBeNull()
        ->and(Carbon::parse($row->completed_at)->toDateTimeString())->toBe('2026-03-07 12:38:29')
        ->and((int) $row->is_explicitly_assigned)->toBe(0)
        ->and(pivotRow($course, $starter))->toBeNull();
});

test('backfill with steps-existing-at-completion never moves an existing completed_at', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = documentStep($lesson, 1);
    $user = learner('kept@example.com');

    $step->complete($user);
    DB::table('lms_course_user')->where('course_id', $course->id)->update(['completed_at' => '2026-01-01 00:00:00']);

    $this->artisan('filament-lms:backfill-course-completed-at', [
        '--course' => [$course->id],
        '--steps-existing-at-completion' => true,
    ])->assertSuccessful();

    expect(Carbon::parse(pivotRow($course, $user)->completed_at)->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

test('backfill restores a learner who finished and then completed a step added later', function () {
    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    Carbon::setTestNow('2026-03-01 10:00:00');
    $original = documentStep($lesson, 1);
    $learner = learner('returned@example.com');

    Carbon::setTestNow('2026-03-07 12:38:29');
    $original->complete($learner);
    DB::table('lms_course_user')->where('course_id', $course->id)->delete();

    Carbon::setTestNow('2026-09-25 00:24:00');
    $added = documentStep($lesson, 2);
    documentStep($lesson, 3);

    Carbon::setTestNow('2026-10-01 09:00:00');
    $added->complete($learner);
    Carbon::setTestNow();

    $this->artisan('filament-lms:backfill-course-completed-at', [
        '--course' => [$course->id],
        '--steps-existing-at-completion' => true,
    ])->assertSuccessful();

    expect(Carbon::parse(pivotRow($course, $learner)?->completed_at)->toDateTimeString())->toBe('2026-03-07 12:38:29');
});

test('assigning users again restores access and keeps completion', function () {
    $course = Course::factory()->create(['is_private' => true]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = documentStep($lesson, 1);
    $user = learner('reassigned@example.com');

    $course->users()->attach($user->id, ['is_explicitly_assigned' => true]);
    $step->complete($user);
    $completedAt = $course->completedByUserAt($user->id);
    $course->unassignUsers([$user->id]);

    $course->assignUsers([$user->id]);

    expect((int) pivotRow($course, $user)->is_explicitly_assigned)->toBe(1)
        ->and($course->completedByUserAt($user->id))->toBe($completedAt)
        ->and($course->canBeAccessedBy($user))->toBeTrue();
});
