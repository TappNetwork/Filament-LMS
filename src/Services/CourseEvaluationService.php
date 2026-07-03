<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Pages\Step as StepPage;

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

    public function completedPrimaryCourseForEvaluation(Course $evaluationCourse, int|string $userId, ?int $primaryCourseId = null): ?Course
    {
        if (! $this->isEvaluationCourse($evaluationCourse)) {
            return null;
        }

        if ($primaryCourseId !== null) {
            $primaryCourse = $this->primaryCoursesFor($evaluationCourse)
                ->first(fn (Course $primary): bool => $primary->id === $primaryCourseId);

            return $primaryCourse !== null
                && $this->courseMeetsCompletionRequirements($primaryCourse, $userId)
                && $this->evaluationCompletedByUser($primaryCourse, $userId)
                ? $primaryCourse
                : null;
        }

        return $this->primaryCoursesFor($evaluationCourse)
            ->first(fn (Course $primary): bool => $this->courseMeetsCompletionRequirements($primary, $userId)
                && $this->evaluationCompletedByUser($primary, $userId));
    }

    public function courseMeetsCompletionRequirements(Course $course, int|string $userId): bool
    {
        if (! $course->allStepsCompletedByUser($userId)) {
            return false;
        }

        if ($course->required_test_percentage === null) {
            return true;
        }

        $testSteps = $course->getOrderedTestSteps();

        if ($testSteps->isEmpty()) {
            return true;
        }

        return $course->getOverallTestPercentageForUser($userId) >= (float) $course->required_test_percentage;
    }

    public function hasPendingEvaluationForUser(Course $primaryCourse, int|string $userId): bool
    {
        return $this->hasEvaluation($primaryCourse)
            && $this->evaluationCourseFor($primaryCourse) !== null
            && $this->courseMeetsCompletionRequirements($primaryCourse, $userId)
            && ! $this->evaluationCompletedByUser($primaryCourse, $userId);
    }

    public function evaluationCompletedByUser(Course $primaryCourse, int|string $userId): bool
    {
        if (! $this->hasEvaluation($primaryCourse)) {
            return true;
        }

        $evaluationCourse = $this->evaluationCourseFor($primaryCourse);

        if ($evaluationCourse === null) {
            return true;
        }

        return $this->evaluationStepsCompletedByUser($evaluationCourse, $userId, $primaryCourse->id);
    }

    public function evaluationUrlForPrimaryCourse(Course $primaryCourse): ?string
    {
        if (! $this->hasEvaluation($primaryCourse)) {
            return null;
        }

        $evaluationCourse = $this->evaluationCourseFor($primaryCourse);

        if ($evaluationCourse === null) {
            return null;
        }

        $steps = $evaluationCourse->steps()->ordered()->get();

        if ($steps->isEmpty()) {
            return null;
        }

        $completedStepIds = $this->completedEvaluationStepIdsForPrimary(
            $evaluationCourse,
            Auth::id(),
            $primaryCourse->id,
        );

        $step = $steps->first(fn (Step $step): bool => ! $completedStepIds->contains($step->id))
            ?? $steps->first();

        return $step instanceof Step
            ? StepPage::getUrlForStep($step, ['primaryCourse' => $primaryCourse->id])
            : null;
    }

    public function ensureEvaluationAssigned(Course $primaryCourse, int|string $userId): void
    {
        if (! $this->hasEvaluation($primaryCourse)) {
            return;
        }

        if (! $this->courseMeetsCompletionRequirements($primaryCourse, $userId)) {
            return;
        }

        $evaluationCourse = $this->evaluationCourseFor($primaryCourse);

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
        if (! $this->courseMeetsCompletionRequirements($primary, $user->id)) {
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

    public function activePrimaryCourseForEvaluation(Course $evaluationCourse, Authenticatable $user, ?int $primaryCourseId = null): ?Course
    {
        if (! $this->isEvaluationCourse($evaluationCourse)) {
            return null;
        }

        $eligiblePrimaryCourses = $this->primaryCoursesFor($evaluationCourse)
            ->filter(fn (Course $primary): bool => $this->isPrimaryTrainingCompletedForUser($primary, $user));

        if ($primaryCourseId !== null) {
            return $eligiblePrimaryCourses
                ->first(fn (Course $primary): bool => $primary->id === $primaryCourseId);
        }

        return $eligiblePrimaryCourses
            ->first(fn (Course $primary): bool => ! $this->evaluationCompletedByUser($primary, $user->id))
            ?? $eligiblePrimaryCourses->first();
    }

    public function evaluationStepAvailableForPrimary(Step $step, int|string $userId, int $primaryCourseId): bool
    {
        if ($step->lesson->course->evaluation_course_id !== null) {
            return true;
        }

        $previousStepIds = $step->lesson->course->steps()
            ->where(function ($query) use ($step): void {
                $query->where('lms_lessons.order', '<', $step->lesson->order)
                    ->orWhere(function ($query) use ($step): void {
                        $query->where('lms_lessons.order', '=', $step->lesson->order)
                            ->where('lms_steps.order', '<', $step->order);
                    });
            })
            ->pluck('lms_steps.id');

        if ($previousStepIds->isEmpty()) {
            return true;
        }

        $completedPreviousStepIds = StepUser::query()
            ->whereIn('step_id', $previousStepIds)
            ->where('user_id', $userId)
            ->where('evaluation_primary_course_id', $primaryCourseId)
            ->whereNotNull('completed_at')
            ->pluck('step_id')
            ->unique();

        return $completedPreviousStepIds->count() === $previousStepIds->count();
    }

    public function finalizePrimaryCoursesAfterEvaluation(Course $evaluationCourse, int|string $userId): void
    {
        if (! $this->isEvaluationCourse($evaluationCourse)) {
            return;
        }

        foreach ($this->primaryCoursesFor($evaluationCourse) as $primaryCourse) {
            if (! $this->courseMeetsCompletionRequirements($primaryCourse, $userId)) {
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

    public function canUseEvaluationCourseId(?Course $primaryCourse, mixed $evaluationCourseId): bool
    {
        if ($evaluationCourseId === null) {
            return true;
        }

        $evaluationCourse = Course::query()->find($evaluationCourseId);

        if ($evaluationCourse === null) {
            return false;
        }

        return $primaryCourse === null
            ? $this->canSelectEvaluationCourse($evaluationCourse)
            : $this->canLinkEvaluationCourse($primaryCourse, $evaluationCourse);
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

    private function evaluationCourseFor(Course $primaryCourse): ?Course
    {
        /** @var Course|null $evaluationCourse */
        $evaluationCourse = $primaryCourse->evaluationCourse;

        return $evaluationCourse;
    }

    private function evaluationStepsCompletedByUser(Course $evaluationCourse, int|string $userId, int $primaryCourseId): bool
    {
        $stepIds = $evaluationCourse->steps()->pluck('lms_steps.id');

        if ($stepIds->isEmpty()) {
            return false;
        }

        return $this->completedEvaluationStepIdsForPrimary(
            $evaluationCourse,
            $userId,
            $primaryCourseId,
        )->count() === $stepIds->count();
    }

    /**
     * @return Collection<int, int>
     */
    private function completedEvaluationStepIdsForPrimary(Course $evaluationCourse, int|string|null $userId, int $primaryCourseId): Collection
    {
        if ($userId === null) {
            return collect();
        }

        return StepUser::query()
            ->whereIn('step_id', $evaluationCourse->steps()->pluck('lms_steps.id'))
            ->where('user_id', $userId)
            ->where('evaluation_primary_course_id', $primaryCourseId)
            ->whereNotNull('completed_at')
            ->pluck('step_id')
            ->unique()
            ->values();
    }
}
