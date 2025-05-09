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
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email', 'lms_courses.id', 'lms_courses.name');
    }
}
