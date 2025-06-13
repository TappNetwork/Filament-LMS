<?php

namespace Tapp\FilamentLms\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step;

trait HasLmsCourses
{
    /**
     * Get all courses the user is enrolled in.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'lms_step_user', 'user_id', 'step_id')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    /**
     * Get all steps the user has completed.
     */
    public function completedSteps(): BelongsToMany
    {
        return $this->belongsToMany(Step::class, 'lms_step_user')
            ->withPivot('completed_at')
            ->wherePivotNotNull('completed_at')
            ->withTimestamps();
    }

    /**
     * Check if the user has completed a specific course.
     */
    public function hasCompletedCourse(Course $course): bool
    {
        return $course->completedByUserAt($this->id) !== null;
    }

    /**
     * Get the user's progress percentage for a specific course.
     */
    public function getCourseProgress(Course $course): float
    {
        return $course->completion_percentage;
    }

    /**
     * Get progress for all courses, including courses the user hasn't started.
     * Returns a collection keyed by course external_id with completion percentages.
     */
    public function lmsCourseProgress()
    {
        $courseProgress = Course::query()
            ->join('lms_lessons', 'lms_lessons.course_id', '=', 'lms_courses.id')
            ->join('lms_steps', 'lms_steps.lesson_id', '=', 'lms_lessons.id')
            ->join('lms_step_user', 'lms_step_user.step_id', '=', 'lms_steps.id')
            ->where('lms_step_user.user_id', $this->id)
            ->select(
                'lms_courses.id',
                DB::raw('count(lms_step_user.completed_at) as completed_steps'),
            )
            ->groupBy('lms_lessons.course_id')
            ->get();

        // add courses that user has not started
        $allCourseProgress = Course::all()->mapWithKeys(function ($course) use ($courseProgress) {
            if ($progress = $courseProgress->find($course->id)) {
                // @phpstan-ignore-next-line
                $completionPercentage = number_format($progress->completed_steps / $course->steps->count(), 2);
                return [$course->external_id => $completionPercentage];
            }

            return [$course->external_id => 0];
        });

        return $allCourseProgress;
    }

    /**
     * Get the course progress as a JSON encoded attribute.
     */
    protected function courseProgress(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => json_encode($this->lmsCourseProgress())
        );
    }
} 