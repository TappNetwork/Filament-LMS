<?php

namespace Tapp\FilamentLms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class CourseProgressQueryService
{
    /**
     * Build query for course progress reporting. Uses lms_course_user.completed_at as the source
     * of truth for completion (not step-derived calculation).
     *
     * @return Builder|QueryBuilder
     */
    public static function buildQuery()
    {
        return DB::table('lms_course_user')
            ->join('users', 'users.id', '=', 'lms_course_user.user_id')
            ->join('lms_courses', 'lms_courses.id', '=', 'lms_course_user.course_id')
            ->leftJoin('lms_lessons', 'lms_lessons.course_id', '=', 'lms_courses.id')
            ->leftJoin('lms_steps', 'lms_steps.lesson_id', '=', 'lms_lessons.id')
            ->leftJoin('lms_step_user', function ($join): void {
                $join->on('lms_step_user.step_id', '=', 'lms_steps.id')
                    ->on('lms_step_user.user_id', '=', 'lms_course_user.user_id');
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
                DB::raw('(SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id) as total_steps'),
                DB::raw("CASE WHEN lms_course_user.completed_at IS NOT NULL THEN 'Completed' ELSE 'In Progress' END as status"),
            ])
            ->groupBy('lms_course_user.user_id', 'lms_course_user.course_id', 'lms_course_user.completed_at', 'users.id', 'users.first_name', 'users.last_name', 'users.email', 'lms_courses.id', 'lms_courses.name')
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
