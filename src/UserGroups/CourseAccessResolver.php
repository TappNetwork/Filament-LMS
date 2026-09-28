<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\UserGroups;

use Illuminate\Database\Eloquent\Builder;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\Services\CourseEvaluationService;

final class CourseAccessResolver
{
    public function __construct(
        private readonly CourseEvaluationService $evaluationService,
    ) {}

    public function canAccess(Course $course, mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($course->isEvaluationCourse()) {
            if ($this->isLmsAdmin($user)) {
                return true;
            }

            return $this->evaluationService->isEvaluationUnlockedForUser($course, $user)
                && ($this->isExplicitlyAssigned($course, $user) || ! $course->is_private);
        }

        if (! $course->is_private) {
            return true;
        }

        if ($this->isLmsAdmin($user)) {
            return true;
        }

        return $this->isExplicitlyAssigned($course, $user)
            || $this->belongsToAssignedGroup($course, $user);
    }

    public function scopeAccessibleTo(Builder $query, mixed $user): void
    {
        $query->where(function (Builder $q) use ($user): void {
            $q->where('is_private', false)
                ->orWhere(function (Builder $subQ) use ($user): void {
                    $subQ->where('is_private', true)
                        ->where(function (Builder $adminOrAssignedQuery) use ($user): void {
                            if ($this->isLmsAdmin($user)) {
                                $adminOrAssignedQuery->whereRaw('1 = 1');

                                return;
                            }

                            $adminOrAssignedQuery
                                ->where(function (Builder $accessQuery) use ($user): void {
                                    $accessQuery
                                        ->whereHas('users', function (Builder $userQuery) use ($user): void {
                                            $userQuery
                                                ->where('user_id', $user->id)
                                                ->where('lms_course_user.is_explicitly_assigned', true);
                                        })
                                        ->orWhereHas('userGroups', function (Builder $groupQuery) use ($user): void {
                                            $groupQuery
                                                ->where('lms_user_groups.is_active', true)
                                                ->where('lms_user_groups.published_revision', '>', 0)
                                                ->whereExists(function ($membershipQuery) use ($user): void {
                                                    $membershipQuery
                                                        ->selectRaw('1')
                                                        ->from('lms_user_group_memberships')
                                                        ->whereColumn(
                                                            'lms_user_group_memberships.user_group_id',
                                                            'lms_user_groups.id'
                                                        )
                                                        ->whereColumn(
                                                            'lms_user_group_memberships.revision',
                                                            'lms_user_groups.published_revision'
                                                        )
                                                        ->where('lms_user_group_memberships.user_id', $user->id);
                                                });
                                        });
                                });
                        });
                });
        })
            ->whereHas('steps')
            ->when(config('filament-lms.evaluations.enabled', false), function (Builder $query): void {
                $evaluationCourseIds = $this->evaluationService->lockedEvaluationCourseIds();

                if ($evaluationCourseIds->isNotEmpty()) {
                    $query->whereNotIn('lms_courses.id', $evaluationCourseIds);
                }
            });
    }

    public function isExplicitlyAssigned(Course $course, mixed $user): bool
    {
        return $course->users()
            ->where('user_id', $user->id)
            ->wherePivot('is_explicitly_assigned', true)
            ->exists();
    }

    public function belongsToAssignedGroup(Course $course, mixed $user): bool
    {
        if (! app(UserGroupCriteriaRegistry::class)->enabled()) {
            return false;
        }

        return UserGroup::query()
            ->where('is_active', true)
            ->where('published_revision', '>', 0)
            ->whereHas('courses', fn (Builder $q): Builder => $q->where('lms_courses.id', $course->id))
            ->whereHas('memberships', function (Builder $q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->whereColumn('revision', 'lms_user_groups.published_revision');
            })
            ->exists();
    }

    /**
     * Courses the user is assigned to manually (lms_course_user) and/or via active group membership.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeAssignedToUser(Builder $query, mixed $user): void
    {
        if ($user === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $accessQuery) use ($user): void {
            $accessQuery
                ->whereHas('users', function (Builder $userQuery) use ($user): void {
                    $userQuery->where('user_id', $user->id);
                })
                ->orWhere(function (Builder $groupQuery) use ($user): void {
                    if (! app(UserGroupCriteriaRegistry::class)->enabled()) {
                        $groupQuery->whereRaw('0 = 1');

                        return;
                    }

                    $groupQuery->whereHas('userGroups', function (Builder $assignedGroupQuery) use ($user): void {
                        $assignedGroupQuery
                            ->where('lms_user_groups.is_active', true)
                            ->where('lms_user_groups.published_revision', '>', 0)
                            ->whereExists(function ($membershipQuery) use ($user): void {
                                $membershipQuery
                                    ->selectRaw('1')
                                    ->from('lms_user_group_memberships')
                                    ->whereColumn(
                                        'lms_user_group_memberships.user_group_id',
                                        'lms_user_groups.id'
                                    )
                                    ->whereColumn(
                                        'lms_user_group_memberships.revision',
                                        'lms_user_groups.published_revision'
                                    )
                                    ->where('lms_user_group_memberships.user_id', $user->id);
                            });
                    });
                });
        });
    }

    private function isLmsAdmin(mixed $user): bool
    {
        return is_object($user)
            && method_exists($user, 'isLmsAdmin')
            && $user->isLmsAdmin();
    }
}
