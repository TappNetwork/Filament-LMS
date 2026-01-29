<?php

namespace Tapp\FilamentLms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CourseProgressQueryService
{
    /**
     * Build query for course progress reporting. Starts from step activity (lms_step_user) so
     * in-progress users appear even when they are not yet in lms_course_user. Completion comes
     * from lms_course_user.completed_at when present.
     *
     * @return Builder|QueryBuilder
     */
    public static function buildQuery()
    {
        $participants = DB::table('lms_step_user')
            ->select('lms_step_user.user_id')
            ->selectRaw('l.course_id')
            ->join('lms_steps as s', 's.id', '=', 'lms_step_user.step_id')
            ->join('lms_lessons as l', 'l.id', '=', 's.lesson_id')
            ->distinct();

        return DB::table(DB::raw('('.$participants->toSql().') as participants'))
            ->mergeBindings($participants)
            ->leftJoin('lms_course_user', function ($join): void {
                $join->on('lms_course_user.user_id', '=', 'participants.user_id')
                    ->on('lms_course_user.course_id', '=', 'participants.course_id');
            })
            ->join('users', 'users.id', '=', 'participants.user_id')
            ->join('lms_courses', 'lms_courses.id', '=', 'participants.course_id')
            ->leftJoin('lms_lessons', 'lms_lessons.course_id', '=', 'participants.course_id')
            ->leftJoin('lms_steps', 'lms_steps.lesson_id', '=', 'lms_lessons.id')
            ->leftJoin('lms_step_user', function ($join): void {
                $join->on('lms_step_user.step_id', '=', 'lms_steps.id')
                    ->on('lms_step_user.user_id', '=', 'participants.user_id');
            })
            ->select([
                'users.id as user_id',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name',
                'users.email as user_email',
                'lms_courses.id as course_id',
                'lms_courses.name as course_name',
                DB::raw('MIN(lms_step_user.created_at) as started_at'),
                'lms_course_user.completed_at as completed_at',
                'lms_course_user.completed_at as completion_date',
                DB::raw('COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) as steps_completed'),
                DB::raw('(SELECT COUNT(DISTINCT s2.id) FROM lms_steps s2 JOIN lms_lessons l2 ON s2.lesson_id = l2.id WHERE l2.course_id = lms_courses.id) as total_steps'),
                DB::raw("CASE WHEN lms_course_user.completed_at IS NOT NULL THEN 'Completed' ELSE 'In Progress' END as status"),
            ])
            ->groupBy('participants.user_id', 'participants.course_id', 'lms_course_user.completed_at', 'users.id', 'users.first_name', 'users.last_name', 'users.email', 'lms_courses.id', 'lms_courses.name')
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByStatus($query, $direction)
    {
        return $query->reorder()
            ->orderByRaw("CASE WHEN lms_course_user.completed_at IS NOT NULL THEN 1 ELSE 0 END {$direction}")
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByFirstName($query, $direction)
    {
        return $query->reorder()
            ->orderBy('users.first_name', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByLastName($query, $direction)
    {
        return $query->reorder()
            ->orderBy('users.last_name', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByEmail($query, $direction)
    {
        return $query->reorder()
            ->orderBy('users.email', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByCourseName($query, $direction)
    {
        return $query->reorder()
            ->orderBy('lms_courses.name', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByStepsCompleted($query, $direction)
    {
        return $query->reorder()
            ->orderBy('steps_completed', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByStartedAt($query, $direction)
    {
        return $query->reorder()
            ->orderBy('started_at', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }

    /**
     * @param  Builder|QueryBuilder  $query
     * @return Builder|QueryBuilder
     */
    public static function sortByCompletedAt($query, $direction)
    {
        return $query->reorder()
            ->orderBy('lms_course_user.completed_at', $direction)
            ->orderBy('users.id', 'asc')
            ->orderBy('lms_courses.id', 'asc');
    }
}
