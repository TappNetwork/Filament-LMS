<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Observers;

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CourseUserGroup;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\Services\CourseAssignmentNotifier;

final class CourseUserGroupObserver
{
    public function created(CourseUserGroup $pivot): void
    {
        $course = Course::query()->find($pivot->course_id);
        $group = UserGroup::query()->find($pivot->user_group_id);

        if (! $course instanceof Course || ! $group instanceof UserGroup) {
            return;
        }

        app(CourseAssignmentNotifier::class)->dispatchAttachedGroup($course, $group);
    }
}
