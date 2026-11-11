<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Actions\AssignCoursesBulkAction;
use Tapp\FilamentLms\Events\CourseAssigned;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Tests\TestUser;

/**
 * @param  array<int, int|string>  $courseIds
 * @return list<array{query: string, bindings: array<int, mixed>, time: float}>
 */
function runAssignCoursesBulkAction(Collection $records, array $courseIds): array
{
    $action = AssignCoursesBulkAction::make();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $action->evaluate($action->getActionFunction(), [
        'records' => $records,
        'data' => ['courses' => $courseIds],
    ]);

    return DB::getQueryLog();
}

function assignCoursesBulkUser(string $name): TestUser
{
    return TestUser::query()->create([
        'name' => $name,
        'email' => Str::uuid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

function assignCoursesBulkIgnoredRecord(): Model
{
    return new class extends Model
    {
        public function getKey(): int
        {
            return 999999;
        }
    };
}

it('assigns every selected user to each course in one batch', function (): void {
    Event::fake([CourseAssigned::class]);

    $users = new Collection([
        assignCoursesBulkUser('Ada'),
        assignCoursesBulkUser('Grace'),
        assignCoursesBulkUser('Lin'),
        assignCoursesBulkUser('Radia'),
    ]);
    $courses = new Collection([
        Course::factory()->create(),
        Course::factory()->create(),
        Course::factory()->create(),
    ]);
    $ignored = assignCoursesBulkIgnoredRecord();

    $queries = runAssignCoursesBulkAction(
        new Collection([...$users->all(), $ignored]),
        $courses->modelKeys(),
    );

    $dispatchLookups = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains($query['query'], 'from "users"')
            && str_contains($query['query'], 'order by "id"'),
    ));
    $pivotReads = array_values(array_filter(
        $queries,
        fn (array $query): bool => $query['query'] === 'select * from "lms_course_user" where "lms_course_user"."course_id" = ?',
    ));

    expect($dispatchLookups)->toHaveCount($courses->count())
        ->and($pivotReads)->toHaveCount($courses->count());

    foreach ($dispatchLookups as $query) {
        expect($query['bindings'])->toHaveCount($users->count());
    }

    expect($users->every(function (TestUser $user) use ($courses): bool {
        return $user->courses()->wherePivot('is_explicitly_assigned', true)->count() === $courses->count();
    }))->toBeTrue()
        ->and(DB::table('lms_course_user')->where('user_id', $ignored->getKey())->exists())->toBeFalse();

    Event::assertDispatchedTimes(CourseAssigned::class, $users->count() * $courses->count());
});

it('keeps completion and does not email users who are already explicitly assigned', function (): void {
    Event::fake([CourseAssigned::class]);

    $returning = assignCoursesBulkUser('Returning');
    $newcomer = assignCoursesBulkUser('Newcomer');
    $course = Course::factory()->create();
    $completedAt = '2026-03-07 12:38:29';

    $course->users()->attach($returning->id, [
        'completed_at' => $completedAt,
        'is_explicitly_assigned' => true,
    ]);

    runAssignCoursesBulkAction(
        new Collection([$returning, $newcomer]),
        [$course->id],
    );

    $row = DB::table('lms_course_user')
        ->where('course_id', $course->id)
        ->where('user_id', $returning->id)
        ->first();

    expect(DB::table('lms_course_user')->where('course_id', $course->id)->count())->toBe(2)
        ->and($row)->not->toBeNull()
        ->and($row->completed_at)->toBe($completedAt)
        ->and((int) $row->is_explicitly_assigned)->toBe(1);

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
    Event::assertDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($newcomer) && $event->course->is($course),
    );
});

it('does not query courses when none of the selected records can take an assignment', function (): void {
    $course = Course::factory()->create();

    $queries = runAssignCoursesBulkAction(
        new Collection([assignCoursesBulkIgnoredRecord()]),
        [$course->id],
    );

    $courseQueries = array_values(array_filter(
        $queries,
        fn (array $query): bool => str_contains($query['query'], 'lms_courses'),
    ));

    expect($courseQueries)->toBeEmpty()
        ->and(DB::table('lms_course_user')->count())->toBe(0);
});
