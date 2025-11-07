<?php

namespace Tapp\FilamentLms\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step;

/**
 * Trait for User models to provide LMS functionality.
 *
 * Classes using this trait should implement FilamentLmsUserInterface
 * to help static analysis tools (like PHPStan) understand the available methods.
 *
 * Example:
 * ```php
 * class User extends Authenticatable implements FilamentLmsUserInterface
 * {
 *     use FilamentLmsUser;
 * }
 * ```
 */
trait FilamentLmsUser
{
    /**
     * Get all courses the user is assigned to (via lms_course_user).
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'lms_course_user', 'user_id', 'course_id')->withTimestamps();
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
        $totalSteps = $course->steps()->count();
        if ($totalSteps === 0) {
            return 0;
        }
        $completedSteps = $this->completedSteps()
            ->whereIn('lms_steps.lesson_id', $course->lessons->pluck('id'))
            ->count();

        return ($completedSteps / $totalSteps) * 100;
    }

    /**
     * Determine if the user can access a specific step.
     * This method can be overridden in the User model to provide custom access control logic.
     *
     * @param  Step  $step  The step to check access for
     * @return bool True if the user can access the step, false otherwise
     */
    public function canAccessStep(Step $step): bool
    {
        // Default implementation: check if previous steps are completed
        // Use the protected method directly to avoid circular dependency with available attribute
        return $step->checkPreviousStepsCompleted();
    }

    /**
     * Determine if the user can edit a specific step.
     * This method can be overridden in the User model to provide custom edit permission logic.
     *
     * @param  Step  $step  The step to check edit permissions for
     * @return bool True if the user can edit the step, false otherwise
     */
    public function canEditStep(Step $step): bool
    {
        // Default implementation: no editing permissions
        return false;
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

    /**
     * Determine if a course is visible for the user.
     */
    public function isCourseVisibleForUser($course): bool
    {
        return $course->users->contains('id', $this->id);
    }

    /**
     * Check if the user is an LMS admin.
     * This method can be overridden in the User model to implement custom admin logic.
     */
    public function isLmsAdmin(): bool
    {
        // Default implementation - return false
        // This should be overridden in the User model to implement project-specific logic
        return false;
    }
}
