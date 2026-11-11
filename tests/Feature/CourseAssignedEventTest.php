<?php

declare(strict_types=1);

use Filament\QueryBuilder\Constraints\Constraint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Events\CourseAssigned;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\Models\UserGroupMembership;
use Tapp\FilamentLms\Tests\Support\TestUserGroupCriteriaProvider;
use Tapp\FilamentLms\Tests\TestUser;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;

beforeEach(function (): void {
    config([
        'filament-lms.user_groups.criteria_provider' => TestUserGroupCriteriaProvider::class,
        'filament-lms.user_groups.sync_queue' => false,
        'filament-lms.user_groups.refresh_on_user_save' => false,
    ]);
});

/**
 * @return array{version: int, sources: list<array{source: string, rules: array<string, mixed>}>}
 */
function courseAssignedNameRules(string $text): array
{
    return [
        'version' => 1,
        'sources' => [
            [
                'source' => 'user',
                'rules' => [
                    'rule1' => [
                        'type' => 'name',
                        'data' => [
                            Constraint::OPERATOR_SELECT_NAME => 'contains',
                            'settings' => [
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function courseAssignedCourse(bool $private = true): Course
{
    $course = Course::factory()->create([
        'is_private' => $private,
    ]);

    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
    ]);

    Step::factory()->create([
        'lesson_id' => $lesson->id,
    ]);

    return $course;
}

function courseAssignedUser(string $name): TestUser
{
    return TestUser::query()->create([
        'name' => $name,
        'email' => Str::uuid().'@example.com',
        'password' => bcrypt('password'),
    ]);
}

it('dispatches course assigned once when a user is explicitly assigned', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Assigned User');
    $course = courseAssignedCourse();

    $course->assignUsers([$user->id]);
    $course->assignUsers([$user->id]);

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
    Event::assertDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($user) && $event->course->is($course),
    );
});

it('dispatches course assigned for a public course', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Public User');
    $course = courseAssignedCourse(private: false);

    $course->assignUsers([$user->id]);

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
});

it('does not dispatch course assigned when a completion row is stored', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Completed User');
    $course = courseAssignedCourse(private: false);

    $course->users()->attach($user->id, [
        'completed_at' => now(),
        'is_explicitly_assigned' => false,
    ]);

    Event::assertNotDispatched(CourseAssigned::class);

    $course->assignUsers([$user->id]);

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
});

it('does not dispatch course assigned when the user is already assigned through a group', function (): void {
    $user = courseAssignedUser('Match User');
    $course = courseAssignedCourse();
    $group = UserGroup::factory()->create([
        'rules' => courseAssignedNameRules('Match'),
    ]);
    $course->userGroups()->attach($group->id);
    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    Event::fake([CourseAssigned::class]);

    $course->assignUsers([$user->id]);

    Event::assertNotDispatched(CourseAssigned::class);
});

it('dispatches course assigned when a group membership is first published', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Match User');
    $other = courseAssignedUser('Other User');
    $course = courseAssignedCourse();
    $group = UserGroup::factory()->create([
        'rules' => courseAssignedNameRules('Match'),
    ]);
    $course->userGroups()->attach($group->id);

    $synchronizer = app(UserGroupMembershipSynchronizer::class);
    $synchronizer->rebuild($group->fresh());
    $synchronizer->rebuild($group->fresh());

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
    Event::assertDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($user) && $event->course->is($course),
    );
    Event::assertNotDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($other),
    );
});

it('does not dispatch a second course assigned event for a second group on the same course', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Match User');
    $course = courseAssignedCourse();
    $synchronizer = app(UserGroupMembershipSynchronizer::class);

    $firstGroup = UserGroup::factory()->create([
        'rules' => courseAssignedNameRules('Match'),
    ]);
    $course->userGroups()->attach($firstGroup->id);
    $synchronizer->rebuild($firstGroup->fresh());

    $secondGroup = UserGroup::factory()->create([
        'rules' => courseAssignedNameRules('Match'),
    ]);
    $course->userGroups()->attach($secondGroup->id);
    $synchronizer->rebuild($secondGroup->fresh());

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
    Event::assertDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($user) && $event->course->is($course),
    );
});

it('dispatches course assigned when an already published group is attached to a course', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Match User');
    $course = courseAssignedCourse();
    $group = UserGroup::factory()->create([
        'rules' => courseAssignedNameRules('Match'),
        'published_revision' => 1,
    ]);

    UserGroupMembership::query()->create([
        'user_group_id' => $group->id,
        'user_id' => $user->id,
        'revision' => 1,
        'matched_at' => now(),
    ]);

    $course->userGroups()->attach($group->id);

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
    Event::assertDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($user) && $event->course->is($course),
    );
});

it('dispatches course assigned when a user starts matching a group', function (): void {
    Event::fake([CourseAssigned::class]);

    $user = courseAssignedUser('Match User');
    $course = courseAssignedCourse();
    $group = UserGroup::factory()->create([
        'rules' => courseAssignedNameRules('Match'),
        'published_revision' => 1,
    ]);
    $course->userGroups()->attach($group->id);

    $synchronizer = app(UserGroupMembershipSynchronizer::class);
    $synchronizer->refreshUser($user->id);
    $synchronizer->refreshUser($user->id);

    Event::assertDispatchedTimes(CourseAssigned::class, 1);
    Event::assertDispatched(
        CourseAssigned::class,
        fn (CourseAssigned $event): bool => $event->user->is($user) && $event->course->is($course),
    );
});
