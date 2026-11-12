<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Tapp\FilamentLms\Events\CourseAssigned;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\Models\UserGroupMembership;

final class CourseAssignmentNotifier
{
    /**
     * Users in $userIds who are not already explicitly assigned to the course
     * and not in another attached group's published membership.
     *
     * Pass $exceptGroupId when the group being attached already contains these
     * users, so that group is not treated as an existing assignment.
     *
     * @param  array<int, int|string>  $userIds
     * @return list<int|string>
     */
    public function newlyAssignedIds(Course $course, array $userIds, int|string|null $exceptGroupId = null): array
    {
        $userIds = $this->normalizeIds($userIds);

        if ($userIds === []) {
            return [];
        }

        $alreadyAssigned = [];

        foreach (array_chunk($userIds, 500) as $chunk) {
            foreach ($this->explicitlyAssignedIds($course, $chunk) as $userId) {
                $alreadyAssigned[$userId] = true;
            }

            foreach ($this->groupAssignedIds($course, $chunk, $exceptGroupId) as $userId) {
                $alreadyAssigned[$userId] = true;
            }
        }

        return array_values(array_filter(
            $userIds,
            fn (int|string $userId): bool => ! isset($alreadyAssigned[$userId]),
        ));
    }

    /**
     * @param  list<int|string>  $userIds
     */
    public function dispatch(Course $course, array $userIds): void
    {
        $userIds = $this->normalizeIds($userIds);

        if ($userIds === []) {
            return;
        }

        $userModel = config('filament-lms.user_model');

        if (! is_string($userModel) || ! is_a($userModel, Model::class, true)) {
            return;
        }

        $key = (new $userModel)->getKeyName();

        foreach (array_chunk($userIds, 500) as $chunk) {
            $userModel::query()
                ->whereIn($key, $chunk)
                ->orderBy($key)
                ->each(function (Model $user) use ($course): void {
                    CourseAssigned::dispatch($user, $course);
                });
        }
    }

    /**
     * Courses and users who would newly gain an assignment if these users joined the group.
     * Call this before the new membership rows are published.
     *
     * @param  array<int, int|string>  $userIds
     * @return list<array{course_id: int|string, user_ids: list<int|string>}>
     */
    public function assignmentsForNewMembers(UserGroup $group, array $userIds): array
    {
        if (! $group->is_active) {
            return [];
        }

        $userIds = $this->normalizeIds($userIds);

        if ($userIds === []) {
            return [];
        }

        $assignments = [];

        foreach ($group->courses()->get() as $course) {
            $ids = $this->newlyAssignedIds($course, $userIds);

            if ($ids === []) {
                continue;
            }

            $assignments[] = [
                'course_id' => $course->getKey(),
                'user_ids' => $ids,
            ];
        }

        return $assignments;
    }

    /**
     * @param  list<array{course_id: int|string, user_ids: list<int|string>}>  $assignments
     */
    public function dispatchAssignments(array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $course = Course::query()->find($assignment['course_id']);

            if (! $course instanceof Course) {
                continue;
            }

            $this->dispatch($course, $assignment['user_ids']);
        }
    }

    public function dispatchAttachedGroup(Course $course, UserGroup $group): void
    {
        if (! $group->is_active || $group->published_revision < 1) {
            return;
        }

        $memberIds = UserGroupMembership::query()
            ->where('user_group_id', $group->getKey())
            ->where('revision', $group->published_revision)
            ->pluck('user_id')
            ->all();

        $this->dispatch($course, $this->newlyAssignedIds($course, $memberIds, $group->getKey()));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int|string>
     */
    public function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (! is_int($id) && ! (is_string($id) && $id !== '')) {
                continue;
            }

            $normalized[] = is_int($id) || ctype_digit($id) ? (int) $id : $id;
        }

        $unique = [];

        foreach ($normalized as $id) {
            $unique[$id] = $id;
        }

        return array_values($unique);
    }

    /**
     * @param  list<int|string>  $userIds
     * @return list<int|string>
     */
    private function explicitlyAssignedIds(Course $course, array $userIds): array
    {
        $user = $course->users()->getRelated();

        return $this->normalizeIds(
            $course->users()
                ->wherePivot('is_explicitly_assigned', true)
                ->whereIn($user->getTable().'.'.$user->getKeyName(), $userIds)
                ->pluck($user->getTable().'.'.$user->getKeyName())
                ->all()
        );
    }

    /**
     * @param  list<int|string>  $userIds
     * @return list<int|string>
     */
    private function groupAssignedIds(Course $course, array $userIds, int|string|null $exceptGroupId): array
    {
        $groupIds = $course->userGroups()
            ->where('lms_user_groups.is_active', true)
            ->where('lms_user_groups.published_revision', '>', 0)
            ->when(
                $exceptGroupId !== null,
                fn (Builder $query): Builder => $query->where('lms_user_groups.id', '!=', $exceptGroupId),
            )
            ->pluck('lms_user_groups.id');

        if ($groupIds->isEmpty()) {
            return [];
        }

        return $this->normalizeIds(
            UserGroupMembership::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('user_group_id', $groupIds)
                ->whereExists(function (QueryBuilder $query): void {
                    $query->selectRaw('1')
                        ->from('lms_user_groups')
                        ->whereColumn('lms_user_groups.id', 'lms_user_group_memberships.user_group_id')
                        ->whereColumn('lms_user_groups.published_revision', 'lms_user_group_memberships.revision');
                })
                ->pluck('user_id')
                ->all()
        );
    }
}
