<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Events\CourseStarted;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;

final class ScormProgressService
{
    /**
     * @param  array{lesson_status?: string|null, lesson_location?: string|null, suspend_data?: string|null, score?: string|null, html5_complete?: bool|null, html5_progress?: bool|null}  $payload
     */
    public function processCommit(Course $course, Authenticatable $user, array $payload): void
    {
        $this->recordStarted($course, $user);

        $location = $payload['lesson_location'] ?? null;
        if (is_string($location) && $location !== '') {
            $this->markPlayerProgress($course, $user);
            $this->completeStepByLocation($course, $user, $location);
        }

        $suspendData = $payload['suspend_data'] ?? null;
        if (is_string($suspendData) && $suspendData !== '') {
            $this->markPlayerProgress($course, $user);
            $this->completeStepBySuspendData($course, $user, $suspendData);
        }

        if (! empty($payload['html5_progress'])) {
            $this->markPlayerProgress($course, $user);
        }

        $status = mb_strtolower((string) ($payload['lesson_status'] ?? ''));
        if (in_array($status, ['completed', 'passed'], true)) {
            if (
                $course->completedByUserAt($user->getAuthIdentifier()) !== null
                || $this->courseCompletedByUser($course, $user)
            ) {
                return;
            }

            $this->completeAllEligibleSteps($course, $user);
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function attemptManualCourseCompletion(Course $course, Authenticatable $user): array
    {
        if ($this->courseCompletedByUser($course, $user)) {
            return ['ok' => true, 'message' => 'Course already completed.'];
        }

        if (! $this->userMayConfirmCourseCompletion($course, $user)) {
            return [
                'ok' => false,
                'message' => 'Continue the course in the player before marking it complete. Progress in the LMS is recorded when you move through content in the player, or after sufficient time spent in the course.',
            ];
        }

        $this->completeAllEligibleSteps($course, $user);

        return ['ok' => true, 'message' => 'Course marked complete.'];
    }

    public function userMayConfirmCourseCompletion(Course $course, Authenticatable $user): bool
    {
        if (! $course->isEmbeddedPlayer()) {
            return true;
        }

        if ($this->courseCompletedByUser($course, $user)) {
            return true;
        }

        if (! $this->courseStartedByUser($course, $user)) {
            return false;
        }

        if ($this->countCompletedNonLaunchSteps($course, $user) >= 1) {
            return true;
        }

        if ($this->countCompletedEligibleSteps($course, $user) >= 2) {
            return true;
        }

        if ($this->hasRecordedPlayerProgress($course, $user) && $this->meetsMinimumSessionDuration($course, $user)) {
            return true;
        }

        return false;
    }

    public function recordStarted(Course $course, Authenticatable $user): void
    {
        $launchStep = $course->launchStep();
        if ($launchStep === null) {
            return;
        }

        $userStep = StepUser::query()->firstOrCreate([
            'user_id' => $user->getAuthIdentifier(),
            'step_id' => $launchStep->id,
        ]);

        if ($userStep->wasRecentlyCreated) {
            CourseStarted::dispatch($user, $course);
        }
    }

    public function markPlayerProgress(Course $course, Authenticatable $user): void
    {
        Cache::put(
            $this->playerProgressCacheKey($course, $user),
            true,
            now()->addDays(7),
        );
    }

    public function hasRecordedPlayerProgress(Course $course, Authenticatable $user): bool
    {
        return Cache::get($this->playerProgressCacheKey($course, $user), false) === true;
    }

    public function completeAllEligibleSteps(Course $course, Authenticatable $user): void
    {
        $course->loadMissing(['lessons.steps']);

        foreach ($this->eligibleStepsForBulkComplete($course) as $step) {
            $step->complete($user);
        }
    }

    public function completeStepByLocation(Course $course, Authenticatable $user, string $location): void
    {
        $step = $this->findStepByPlayerReference($course, $location);

        if ($step !== null) {
            $this->completeStepsUpTo($course, $user, $step);
        }
    }

    public function completeStepBySuspendData(Course $course, Authenticatable $user, string $suspendData): void
    {
        $furthestStep = null;

        foreach ($this->orderedEligibleSteps($course) as $step) {
            if ($step->player_slide_id === null || $step->player_slide_id === '') {
                continue;
            }

            if (str_contains($suspendData, (string) $step->player_slide_id)) {
                $furthestStep = $step;
            }
        }

        if ($furthestStep instanceof Step) {
            $this->completeStepsUpTo($course, $user, $furthestStep);
        }
    }

    public function courseStartedByUser(Course $course, Authenticatable $user): bool
    {
        $launchStep = $course->launchStep();
        if ($launchStep === null) {
            return false;
        }

        return StepUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('step_id', $launchStep->id)
            ->exists();
    }

    public function courseCompletedByUser(Course $course, Authenticatable $user): bool
    {
        $eligible = $this->eligibleStepsForBulkComplete($course);
        if ($eligible->isEmpty()) {
            return false;
        }

        $completedCount = StepUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('step_id', $eligible->pluck('id'))
            ->whereNotNull('completed_at')
            ->count();

        return $completedCount >= $eligible->count();
    }

    public function meetsMinimumSessionDuration(Course $course, Authenticatable $user): bool
    {
        $launchStep = $course->launchStep();
        if ($launchStep === null) {
            return false;
        }

        $userStep = StepUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('step_id', $launchStep->id)
            ->first();

        if ($userStep === null) {
            return false;
        }

        $minimumSeconds = $course->completionMode() === CompletionMode::Html5
            ? (int) config('filament-lms.embedded_player_min_session_seconds_html5', 300)
            : (int) config('filament-lms.embedded_player_min_session_seconds', 90);

        return $userStep->created_at !== null
            && $userStep->created_at->diffInSeconds(now()) >= $minimumSeconds;
    }

    /**
     * @return Collection<int, Step>
     */
    private function eligibleStepsForBulkComplete(Course $course): Collection
    {
        return $course->steps->filter(fn (Step $step): bool => $step->material_type !== 'test');
    }

    private function countCompletedEligibleSteps(Course $course, Authenticatable $user): int
    {
        $stepIds = $this->eligibleStepsForBulkComplete($course)->pluck('id');

        return StepUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('step_id', $stepIds)
            ->whereNotNull('completed_at')
            ->count();
    }

    private function countCompletedNonLaunchSteps(Course $course, Authenticatable $user): int
    {
        $launchStep = $course->launchStep();
        if ($launchStep === null) {
            return 0;
        }

        $stepIds = $this->eligibleStepsForBulkComplete($course)
            ->reject(fn (Step $step): bool => $step->is($launchStep))
            ->pluck('id');

        if ($stepIds->isEmpty()) {
            return 0;
        }

        return StepUser::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereIn('step_id', $stepIds)
            ->whereNotNull('completed_at')
            ->count();
    }

    private function playerProgressCacheKey(Course $course, Authenticatable $user): string
    {
        return 'lms_player_progress_'.$course->id.'_'.$user->getAuthIdentifier();
    }

    /**
     * @return Collection<int, Step>
     */
    private function orderedSteps(Course $course): Collection
    {
        $course->loadMissing(['lessons.steps']);

        return $course->lessons
            ->sortBy('order')
            ->flatMap(fn ($lesson) => $lesson->steps->sortBy('order'))
            ->values();
    }

    /**
     * @return Collection<int, Step>
     */
    private function orderedEligibleSteps(Course $course): Collection
    {
        return $this->orderedSteps($course)
            ->filter(fn (Step $step): bool => $step->material_type !== 'test')
            ->values();
    }

    private function completeStepsUpTo(Course $course, Authenticatable $user, Step $targetStep): void
    {
        // Include tests in iteration so a test target still stops the cascade.
        foreach ($this->orderedSteps($course) as $step) {
            if ($step->material_type !== 'test') {
                $step->complete($user);
            }

            if ($step->is($targetStep)) {
                break;
            }
        }
    }

    private function findStepByPlayerReference(Course $course, string $location): ?Step
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        $exact = $course->steps()->where('player_slide_id', $location)->first();
        if ($exact instanceof Step) {
            return $exact;
        }

        $locationSegment = $this->extractPlayerSlideSegment($location);

        if ($locationSegment !== null) {
            $segmentMatch = $course->steps()->where('player_slide_id', $locationSegment)->first();
            if ($segmentMatch instanceof Step) {
                return $segmentMatch;
            }
        }

        $matchingStep = $course->steps()
            ->whereNotNull('player_slide_id')
            ->get()
            ->first(fn (Step $step): bool => $step->player_slide_id !== null
                && (str_contains($location, $step->player_slide_id)
                    || str_contains($step->player_slide_id, $location)));

        return $matchingStep instanceof Step ? $matchingStep : null;
    }

    private function extractPlayerSlideSegment(string $location): ?string
    {
        if (! str_contains($location, '_player.') && ! str_contains($location, '.')) {
            return null;
        }

        $parts = explode('.', $location);
        $last = end($parts);

        return $last !== '' ? $last : null;
    }
}
