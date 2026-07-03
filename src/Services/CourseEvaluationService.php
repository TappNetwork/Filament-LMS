<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\UniqueConstraintViolationException;
use Tapp\FilamentLms\Models\Course;

final class CourseEvaluationService
{
    public function evaluationsEnabled(): bool
    {
        return (bool) config('filament-lms.evaluations.enabled', false);
    }

    public function hasEvaluation(Course $course): bool
    {
        return $this->evaluationsEnabled()
            && $course->evaluation_course_id !== null;
    }

    public function isEvaluationCourse(Course $course): bool
    {
        if (! $this->evaluationsEnabled()) {
            return false;
        }

        return Course::query()
            ->where('evaluation_course_id', $course->id)
            ->exists();
    }

    /**
     * @return Collection<int, Course>
     */
    public function primaryCoursesFor(Course $evaluationCourse): Collection
    {
        return Course::query()
            ->where('evaluation_course_id', $evaluationCourse->id)
            ->get();
    }

    public function completedPrimaryCourseForEvaluation(Course $evaluationCourse, int|string $userId): ?Course
    {
        if (! $this->isEvaluationCourse($evaluationCourse)) {
            return null;
        }

        return $this->primaryCoursesFor($evaluationCourse)
            ->first(fn (Course $primary): bool => $primary->allStepsCompletedByUser($userId));
    }

    public function evaluationCompletedByUser(Course $primaryCourse, int|string $userId): bool
    {
        if (! $this->hasEvaluation($primaryCourse)) {
            return true;
        }

        $evaluationCourse = $primaryCourse->evaluationCourse;

        if ($evaluationCourse === null) {
            return true;
        }

        return $evaluationCourse->allStepsCompletedByUser($userId);
    }

    public function ensureEvaluationAssigned(Course $primaryCourse, int|string $userId): void
    {
        if (! $this->hasEvaluation($primaryCourse)) {
            return;
        }

        $evaluationCourse = $primaryCourse->evaluationCourse;

        if ($evaluationCourse === null) {
            return;
        }

        if ($evaluationCourse->users()->where('user_id', $userId)->exists()) {
            return;
        }

        try {
            $evaluationCourse->users()->attach($userId);
        } catch (UniqueConstraintViolationException) {
            // Already assigned by a concurrent request.
        }
    }

    public function isPrimaryTrainingCompletedForUser(Course $primary, Authenticatable $user): bool
    {
        if (! $primary->allStepsCompletedByUser($user->id)) {
            return false;
        }

        if (! $primary->is_private) {
            return true;
        }

        return $primary->users()->where('user_id', $user->id)->exists();
    }

    public function isEvaluationUnlockedForUser(Course $evaluationCourse, Authenticatable $user): bool
    {
        if (! $this->isEvaluationCourse($evaluationCourse)) {
            return true;
        }

        return $this->primaryCoursesFor($evaluationCourse)
            ->contains(fn (Course $primary): bool => $this->isPrimaryTrainingCompletedForUser($primary, $user));
    }

    public function finalizePrimaryCoursesAfterEvaluation(Course $evaluationCourse, int|string $userId): void
    {
        if (! $this->isEvaluationCourse($evaluationCourse)) {
            return;
        }

        foreach ($this->primaryCoursesFor($evaluationCourse) as $primaryCourse) {
            if (! $primaryCourse->allStepsCompletedByUser($userId)) {
                continue;
            }

            $primaryCourse->maybeSetCompletedAtForUser($userId);
        }
    }

    /**
     * @return Collection<int, int>
     */
    public function unlockedEvaluationCourseIdsForUser(Authenticatable $user): Collection
    {
        if (! $this->evaluationsEnabled()) {
            return collect();
        }

        $evaluationCourseIds = Course::query()
            ->whereNotNull('evaluation_course_id')
            ->pluck('evaluation_course_id')
            ->unique()
            ->values();

        return $evaluationCourseIds->filter(function (int $evaluationCourseId) use ($user): bool {
            $evaluationCourse = Course::query()->find($evaluationCourseId);

            if ($evaluationCourse === null) {
                return false;
            }

            return $this->isEvaluationUnlockedForUser($evaluationCourse, $user);
        })->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function lockedEvaluationCourseIds(): Collection
    {
        if (! $this->evaluationsEnabled()) {
            return collect();
        }

        return Course::query()
            ->whereNotNull('evaluation_course_id')
            ->pluck('evaluation_course_id')
            ->unique()
            ->values();
    }

    public function isValidEvaluationTarget(?Course $course): bool
    {
        if ($course === null) {
            return false;
        }

        return $course->evaluation_course_id === null;
    }

    public function canSelectEvaluationCourse(?Course $evaluationCourse): bool
    {
        if ($evaluationCourse === null) {
            return true;
        }

        if (! $evaluationCourse->is_private) {
            return false;
        }

        return $this->isValidEvaluationTarget($evaluationCourse);
    }

    public function canLinkEvaluationCourse(Course $primaryCourse, ?Course $evaluationCourse): bool
    {
        if ($evaluationCourse === null) {
            return true;
        }

        if ($primaryCourse->is($evaluationCourse)) {
            return false;
        }

        if ($this->isEvaluationCourse($primaryCourse)) {
            return false;
        }

        return $this->canSelectEvaluationCourse($evaluationCourse);
    }
}
