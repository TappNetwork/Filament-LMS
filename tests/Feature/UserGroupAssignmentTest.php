<?php

declare(strict_types=1);

use Filament\QueryBuilder\Constraints\Constraint;
use Illuminate\Support\Facades\Queue;
use Tapp\FilamentLms\Jobs\RebuildUserGroupMemberships;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\Tests\Support\TestUserGroupCriteriaProvider;
use Tapp\FilamentLms\Tests\TestUser;
use Tapp\FilamentLms\UserGroups\CourseAccessResolver;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;
use Tapp\FilamentLms\UserGroups\UserGroupRuleMatcher;

beforeEach(function (): void {
    config([
        'filament-lms.user_groups.criteria_provider' => TestUserGroupCriteriaProvider::class,
        'filament-lms.user_groups.sync_queue' => false,
        'filament-lms.user_groups.refresh_on_user_save' => false,
    ]);
});

function makeNameContainsRules(string $text): array
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

function makePrivateCourseWithStep(): Course
{
    $course = Course::factory()->create([
        'is_private' => true,
    ]);

    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
    ]);

    Step::factory()->create([
        'lesson_id' => $lesson->id,
    ]);

    return $course->fresh();
}

it('registers configured criteria sources', function (): void {
    $sources = app(UserGroupCriteriaRegistry::class)->sources();

    expect($sources)->toHaveKey('user')
        ->and($sources['user']->label)->toBe('User');
});

it('rejects unknown criteria sources', function (): void {
    app(UserGroupRuleMatcher::class)->normalize([
        'version' => 1,
        'sources' => [
            [
                'source' => 'unknown',
                'rules' => [],
            ],
        ],
    ]);
})->throws(InvalidArgumentException::class);

it('matches users with text constraints', function (): void {
    $alice = TestUser::query()->create([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => bcrypt('password'),
    ]);
    TestUser::query()->create([
        'name' => 'Bob Jones',
        'email' => 'bob@example.com',
        'password' => bcrypt('password'),
    ]);

    $matcher = app(UserGroupRuleMatcher::class);
    $ids = $matcher->matchingUsersQuery(makeNameContainsRules('Alice'))->pluck('id');

    expect($ids)->toContain($alice->id)
        ->and($ids)->toHaveCount(1);
});

it('rebuilds memberships atomically and grants private course access', function (): void {
    $matching = TestUser::query()->create([
        'name' => 'Match User',
        'email' => 'match@example.com',
        'password' => bcrypt('password'),
    ]);
    $other = TestUser::query()->create([
        'name' => 'Other User',
        'email' => 'other@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = makePrivateCourseWithStep();
    $group = UserGroup::factory()->create([
        'rules' => makeNameContainsRules('Match'),
    ]);
    $course->userGroups()->attach($group->id);

    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    $group->refresh();

    expect($group->published_revision)->toBe(1)
        ->and($group->hasPublishedMembership($matching->id))->toBeTrue()
        ->and($group->hasPublishedMembership($other->id))->toBeFalse();

    $resolver = app(CourseAccessResolver::class);

    expect($resolver->canAccess($course, $matching))->toBeTrue()
        ->and($resolver->canAccess($course, $other))->toBeFalse();

    expect(
        Course::query()->accessibleTo($matching)->whereKey($course->id)->exists()
    )->toBeTrue()
        ->and(
            Course::query()->accessibleTo($other)->whereKey($course->id)->exists()
        )->toBeFalse();
});

it('revokes group access when membership no longer matches after rebuild', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Match User',
        'email' => 'match@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = makePrivateCourseWithStep();
    $group = UserGroup::factory()->create([
        'rules' => makeNameContainsRules('Match'),
    ]);
    $course->userGroups()->attach($group->id);

    $synchronizer = app(UserGroupMembershipSynchronizer::class);
    $synchronizer->rebuild($group->fresh());

    expect(app(CourseAccessResolver::class)->canAccess($course, $user))->toBeTrue();

    $group->forceFill([
        'rules' => makeNameContainsRules('Nobody'),
    ])->save();
    $synchronizer->rebuild($group->fresh());

    expect(app(CourseAccessResolver::class)->canAccess($course, $user))->toBeFalse();
});

it('keeps completion history when access is only from a group and completion attaches without explicit assignment', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Match User',
        'email' => 'match@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = makePrivateCourseWithStep();
    $group = UserGroup::factory()->create([
        'rules' => makeNameContainsRules('Match'),
    ]);
    $course->userGroups()->attach($group->id);
    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    $course->users()->attach($user->id, [
        'completed_at' => now(),
        'is_explicitly_assigned' => false,
    ]);

    $pivot = $course->users()->where('user_id', $user->id)->first()?->pivot;

    expect($pivot)->not->toBeNull()
        ->and($pivot->completed_at)->not->toBeNull()
        ->and((bool) $pivot->is_explicitly_assigned)->toBeFalse()
        ->and(app(CourseAccessResolver::class)->canAccess($course, $user))->toBeTrue();

    $group->forceFill([
        'rules' => makeNameContainsRules('Nobody'),
    ])->save();
    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    expect(app(CourseAccessResolver::class)->canAccess($course, $user))->toBeFalse()
        ->and($course->completedByUserAt($user->id))->not->toBeNull();
});

it('prefers explicit manual assignment over group membership for access', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Manual User',
        'email' => 'manual@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = makePrivateCourseWithStep();
    $course->users()->attach($user->id, ['is_explicitly_assigned' => true]);

    expect(app(CourseAccessResolver::class)->canAccess($course, $user))->toBeTrue();
});

it('lists manually assigned and group-assigned courses for a user', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Match User',
        'email' => 'match@example.com',
        'password' => bcrypt('password'),
    ]);

    $manualCourse = makePrivateCourseWithStep();
    $manualCourse->forceFill(['name' => 'Manual Course '.uniqid()])->save();
    $manualCourse->users()->attach($user->id, ['is_explicitly_assigned' => true]);

    $groupCourse = makePrivateCourseWithStep();
    $groupCourse->forceFill(['name' => 'Group Course '.uniqid()])->save();
    $group = UserGroup::factory()->create([
        'rules' => makeNameContainsRules('Match'),
    ]);
    $groupCourse->userGroups()->attach($group->id);
    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    $unrelated = makePrivateCourseWithStep();
    $unrelated->forceFill(['name' => 'Unrelated Course '.uniqid()])->save();

    $assignedIds = Course::query()->assignedToUser($user)->pluck('id');

    expect($assignedIds)->toContain($manualCourse->id)
        ->and($assignedIds)->toContain($groupCourse->id)
        ->and($assignedIds)->not->toContain($unrelated->id);
});

it('queues membership rebuilds when configured', function (): void {
    config(['filament-lms.user_groups.sync_queue' => true]);
    Queue::fake();

    $group = UserGroup::factory()->create([
        'rules' => makeNameContainsRules('Match'),
    ]);

    app(UserGroupMembershipSynchronizer::class)->queueRebuild($group);

    Queue::assertPushed(RebuildUserGroupMemberships::class, fn (RebuildUserGroupMemberships $job): bool => $job->userGroupId === $group->id);
});

it('refreshes a single user against active groups', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Match User',
        'email' => 'match@example.com',
        'password' => bcrypt('password'),
    ]);

    $group = UserGroup::factory()->create([
        'rules' => makeNameContainsRules('Match'),
        'published_revision' => 1,
    ]);

    app(UserGroupMembershipSynchronizer::class)->refreshUser($user->id);

    expect($group->fresh()->hasPublishedMembership($user->id))->toBeTrue();

    $user->forceFill(['name' => 'Changed'])->save();
    app(UserGroupMembershipSynchronizer::class)->refreshUser($user->id);

    expect($group->fresh()->hasPublishedMembership($user->id))->toBeFalse();
});
