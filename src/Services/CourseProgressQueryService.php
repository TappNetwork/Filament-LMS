<?php

namespace Tapp\FilamentLms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Models\StepUser;

class CourseProgressQueryService
{
    public static function buildQuery(): Builder
    {
        return StepUser::query()
            ->join('users', 'lms_step_user.user_id', '=', 'users.id')
            ->join('lms_steps', 'lms_step_user.step_id', '=', 'lms_steps.id')
            ->join('lms_lessons', 'lms_steps.lesson_id', '=', 'lms_lessons.id')
            ->join('lms_courses', 'lms_lessons.course_id', '=', 'lms_courses.id')
            ->select([
                'users.id as user_id',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name',
                'users.email as user_email',
                'lms_courses.id as course_id',
                'lms_courses.name as course_name',
                DB::raw('MIN(lms_step_user.created_at) as started_at'),
                DB::raw('CASE
                        WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) =
                        (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id)
                        THEN MAX(lms_step_user.completed_at)
                        ELSE NULL
                    END as completed_at'),
                DB::raw('CASE
                        WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) =
                        (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id)
                        THEN MAX(lms_step_user.completed_at)
                        ELSE NULL
                    END as completion_date'),
                DB::raw('COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) as steps_completed'),
                DB::raw('(SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id) as total_steps'),
                DB::raw('CASE
                        WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) =
                        (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id)
                        THEN \'Completed\'
                        ELSE \'In Progress\'
                    END as status'),
            ])
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email', 'lms_courses.id', 'lms_courses.name')
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByStatus($query, $direction): Builder
    {
        // Sort by completion status: Completed first when DESC, In Progress first when ASC
        return $query->reorder()
            ->orderByRaw("CASE
                WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) =
                (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id)
                THEN 1
                ELSE 0
            END {$direction}")
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByFirstName($query, $direction): Builder
    {
        return $query->reorder()
            ->orderBy('users.first_name', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByLastName($query, $direction): Builder
    {
        return $query->reorder()
            ->orderBy('users.last_name', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByEmail($query, $direction): Builder
    {
        return $query->reorder()
            ->orderBy('users.email', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByCourseName($query, $direction): Builder
    {
        return $query->reorder()
            ->orderBy('lms_courses.name', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByStepsCompleted($query, $direction): Builder
    {
        // Sort by the number of completed steps
        return $query->reorder()
            ->orderByRaw("COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) {$direction}")
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByStartedAt($query, $direction): Builder
    {
        return $query->reorder()
            ->orderByRaw("MIN(lms_step_user.created_at) {$direction}")
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    public static function sortByCompletedAt($query, $direction): Builder
    {
        return $query->reorder()
            ->orderByRaw("MAX(lms_step_user.completed_at) {$direction}")
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }
}
