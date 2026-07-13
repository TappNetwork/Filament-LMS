<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Database\Factories\CourseFactory;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Events\CourseCompleted as CourseCompletedEvent;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Services\CourseEvaluationService;
use Tapp\FilamentLms\Traits\HasMediaUrl;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $external_id
 * @property string|null $image
 * @property string|null $award
 * @property array $award_content
 * @property string|null $description
 * @property int|null $required_test_percentage
 * @property bool $is_private
 * @property bool $embedded_player
 * @property CompletionMode|string $completion_mode
 * @property int|null $evaluation_course_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Course|null $evaluationCourse
 * @property-read \Illuminate\Database\Eloquent\Collection|Lesson[] $lessons
 * @property-read \Illuminate\Database\Eloquent\Collection|Step[] $steps
 */
final class Course extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasMediaUrl;
    use InteractsWithMedia;

    protected $guarded = [];

    protected $table = 'lms_courses';

    protected $casts = [
        'award_content' => 'array',
        'is_private' => 'boolean',
        'embedded_player' => 'boolean',
        'completion_mode' => CompletionMode::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('courses')
            ->singleFile();
    }

    /**
     * Scope a query to only include visible courses.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereHas('steps')->where('is_private', false);
    }

    /**
     * Scope a query to only include courses accessible to a specific user.
     */
    public function scopeAccessibleTo(Builder $query, $user): void
    {
        $query->where(function ($q) use ($user) {
            // Public courses - not private, accessible to everyone
            $q->where('is_private', false)
              // Private courses - only accessible to LMS admins or assigned users
                ->orWhere(function ($subQ) use ($user) {
                    $subQ->where('is_private', true)
                        ->where(function ($adminOrAssignedQuery) use ($user) {
                            // LMS admins can see all private courses
                            if ($user->isLmsAdmin()) {
                                $adminOrAssignedQuery->whereRaw('1 = 1'); // Always true for admins
                            } else {
                                // Non-admins can only see assigned courses
                                $adminOrAssignedQuery->whereHas('users', function ($userQuery) use ($user) {
                                    $userQuery->where('user_id', $user->id);
                                });
                            }
                        });
                });
        })
        // Only include courses that have at least one step
            ->whereHas('steps')
            ->when(config('filament-lms.evaluations.enabled', false), function (Builder $query): void {
                $evaluationCourseIds = app(CourseEvaluationService::class)->lockedEvaluationCourseIds();

                if ($evaluationCourseIds->isNotEmpty()) {
                    $query->whereNotIn('lms_courses.id', $evaluationCourseIds);
                }
            });
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function evaluationCourse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'evaluation_course_id');
    }

    public function hasEvaluation(): bool
    {
        return app(CourseEvaluationService::class)->hasEvaluation($this);
    }

    public function isEvaluationCourse(): bool
    {
        return app(CourseEvaluationService::class)->isEvaluationCourse($this);
    }

    public function evaluationCompletedByUser(int|string $userId): bool
    {
        return app(CourseEvaluationService::class)->evaluationCompletedByUser($this, $userId);
    }

    public function ensureEvaluationAssigned(int|string $userId): void
    {
        app(CourseEvaluationService::class)->ensureEvaluationAssigned($this, $userId);
    }

    public function evaluationSubmissionUrl(): ?string
    {
        return app(CourseEvaluationService::class)->evaluationUrlForPrimaryCourse($this);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->ordered();
    }

    public function isEmbeddedPlayer(): bool
    {
        return (bool) $this->embedded_player;
    }

    public function completionMode(): CompletionMode
    {
        $mode = $this->completion_mode;

        return $mode instanceof CompletionMode
            ? $mode
            : CompletionMode::tryFrom((string) $mode) ?? CompletionMode::Native;
    }

    public function launchStep(): ?Step
    {
        $this->loadMissing(['lessons.steps']);

        foreach ($this->lessons->sortBy('order') as $lesson) {
            foreach ($lesson->steps->sortBy('order') as $step) {
                if ($step->material_type !== 'document') {
                    continue;
                }

                $material = $step->material;
                if ($material instanceof Document && $material->hasScormPackage()) {
                    return $step;
                }
            }
        }

        foreach ($this->lessons->sortBy('order') as $lesson) {
            $step = $lesson->steps->sortBy('order')->first();

            if ($step instanceof Step) {
                return $step;
            }
        }

        return null;
    }

    public function linkToCurrentStep(?int $evaluationPrimaryCourseId = null): string
    {
        $evaluationPrimaryCourseId = $this->resolveEvaluationPrimaryCourseIdForCurrentUser($evaluationPrimaryCourseId);

        if ($this->isEmbeddedPlayer()) {
            $launchStep = $this->launchStep();

            return $launchStep !== null
                ? StepPage::getUrlForStep($launchStep)
                : Dashboard::getUrl();
        }

        // Get all steps in order
        $allSteps = $this->steps()->ordered()->get();

        // If course has no steps, return dashboard URL
        if ($allSteps->isEmpty()) {
            return Dashboard::getUrl();
        }

        // Get all completed steps for this user
        $user = Auth::user();

        if (! $user instanceof Authenticatable) {
            return Dashboard::getUrl();
        }

        $completedStepIds = $this->completedStepIdsForUser($allSteps, $user->id, $evaluationPrimaryCourseId);

        // Find the first step that hasn't been completed
        $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
            // @phpstan-ignore-next-line
            $user = Auth::user();
            if (! $user instanceof FilamentLmsUserInterface) {
                return false;
            }

            return ! $completedStepIds->contains($step->id) && $user->canAccessStep($step);
        });

        // If no incomplete step is available, check if course is complete
        if (! $firstIncompleteStep) {
            // @phpstan-ignore-next-line
            if ($completedStepIds->count() === $allSteps->count()) {
                return $this->certificateUrl();
            }
            // If course is not complete but no step is available, find the first incomplete step
            $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
                return ! $completedStepIds->contains($step->id);
            });
        }

        return $firstIncompleteStep
            ? StepPage::getUrlForStep($firstIncompleteStep, $evaluationPrimaryCourseId !== null
                ? ['primaryCourse' => $evaluationPrimaryCourseId]
                : [])
            : Dashboard::getUrl();
    }

    public function currentStep(?Authenticatable $user = null, ?int $evaluationPrimaryCourseId = null): ?Step
    {
        $user = $user ?: Auth::user();
        if (! $user) {
            return null;
        }
        $allSteps = $this->steps;

        // Get all completed steps for this user
        $completedStepIds = $this->completedStepIdsForUser($allSteps, $user->id, $evaluationPrimaryCourseId);

        // Find the first step that hasn't been completed
        $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
            return ! $completedStepIds->contains($step->id);
        });

        return $firstIncompleteStep ?: $allSteps->first();
    }

    public function firstStep(): ?Step
    {
        $firstLesson = $this->lessons()->first();

        return $firstLesson?->steps()->with('lesson')->first();
    }

    public function steps(): HasManyThrough
    {
        return $this->hasManyThrough(Step::class, Lesson::class)
            ->select('lms_steps.*')
            ->orderBy('lms_steps.order');
    }

    public function startedByUserAt($userId): ?string
    {
        return StepUser::whereIn('step_id', $this->steps()->pluck('lms_steps.id'))
            ->where('user_id', $userId)
            ->min('created_at');
    }

    /**
     * When the user completed this course (all steps + passing grade if required).
     * Stored on lms_course_user.completed_at; set when they first qualify (including after retakes).
     */
    public function completedByUserAt($userId): ?string
    {
        if (
            Auth::check()
            && $this->relationLoaded('authEnrollment')
            && (string) $userId === (string) Auth::id()
        ) {
            $first = $this->authEnrollment->first();
            $pivot = $first ? $first->getRelationValue('pivot') : null;

            return $this->formatPivotCompletedAt($pivot instanceof Pivot ? $pivot : null);
        }

        $attached = $this->users()->where('user_id', $userId)->first();
        $pivot = $attached ? $attached->getRelationValue('pivot') : null;

        return $this->formatPivotCompletedAt($pivot instanceof Pivot ? $pivot : null);
    }

    /**
     * Set course completed_at for the user on the pivot when they have completed all steps
     * and (if required) met the test percentage. Called after any step completion so that
     * retaking a test and passing will set completed_at.
     */
    public function maybeSetCompletedAtForUser(int|string $userId): void
    {
        $enrolledUser = $this->users()->where('user_id', $userId)->first();
        $pivot = $enrolledUser?->getRelationValue('pivot');
        $existing = $pivot instanceof Pivot ? $pivot->getAttribute('completed_at') : null;
        $evaluationService = app(CourseEvaluationService::class);

        if ($existing !== null) {
            if ($evaluationService->isEvaluationCourse($this)) {
                $evaluationService->finalizePrimaryCoursesAfterEvaluation($this, $userId);
            }

            return;
        }

        if ($evaluationService->isEvaluationCourse($this)) {
            $evaluationService->finalizePrimaryCoursesAfterEvaluation($this, $userId);

            if ($evaluationService->completedPrimaryCourseForEvaluation($this, $userId) === null) {
                return;
            }
        } elseif (! $evaluationService->courseMeetsCompletionRequirements($this, $userId)) {
            return;
        }

        if ($evaluationService->hasEvaluation($this)) {
            $this->ensureEvaluationAssigned($userId);

            if (! $this->evaluationCompletedByUser($userId)) {
                return;
            }
        }

        $completedAt = now();

        if ($this->users()->where('user_id', $userId)->exists()) {
            $this->users()->updateExistingPivot($userId, ['completed_at' => $completedAt]);
        } else {
            try {
                $this->users()->attach($userId, ['completed_at' => $completedAt]);
            } catch (UniqueConstraintViolationException) {
                $this->users()->updateExistingPivot($userId, ['completed_at' => $completedAt]);
            }
        }

        $completedUser = $this->users()->where('user_id', $userId)->first();

        if ($completedUser !== null) {
            CourseCompletedEvent::dispatch($completedUser, $this);
        }

        if ($evaluationService->isEvaluationCourse($this)) {
            $evaluationService->finalizePrimaryCoursesAfterEvaluation($this, $userId);
        }
    }

    public function getCompletedAtAttribute(): ?string
    {
        if (! Auth::check()) {
            return null;
        }

        return $this->completedByUserAt(Auth::id());
    }

    /**
     * TODO check if progress is already loaded
     * load progress for course and steps
     * make sure steps are in order
     **/
    public function loadProgress()
    {
        $this->load([
            'lessons' => function ($query) {
                $query->orderBy('order');
            },
            'lessons.steps' => function ($query) {
                $query->orderBy('order');
            },
            'lessons.steps.progress',
            // TODO is loading material here necessary?
            // 'lessons.steps.material',
        ]);
    }

    public function getCompletionPercentageAttribute()
    {
        if (! Auth::check()) {
            return 0;
        }

        $evaluationPrimaryCourseId = app(CourseEvaluationService::class)->evaluationPrimaryCourseIdFromRequest();

        return $this->getCompletionPercentageForUser(
            Auth::id(),
            $this->resolveEvaluationPrimaryCourseIdForCurrentUser($evaluationPrimaryCourseId),
        );
    }

    public function getCompletionPercentageForUser($userId, ?int $evaluationPrimaryCourseId = null): float
    {
        if (
            $this->isEmbeddedPlayer()
            && $this->completionMode() === CompletionMode::Scorm12
            && $evaluationPrimaryCourseId === null
        ) {
            return $this->getEmbeddedPlayerCompletionPercentageForUser($userId);
        }

        // Use eager-loaded steps when available to avoid N+1 queries on dashboard
        $steps = $this->relationLoaded('steps') ? $this->steps : $this->steps()->get();

        if ($steps->isEmpty()) {
            return 0;
        }

        // When steps have eager-loaded progress (e.g. from dashboard), use it directly
        // Only safe when $userId matches the authenticated user, since progress is scoped to Auth::id()
        if (
            Auth::check()
            && (string) $userId === (string) Auth::id()
            && $evaluationPrimaryCourseId === null
            && $steps->first()->relationLoaded('progress')
        ) {
            $completedCount = $steps->filter(fn (Step $step) => $step->progress?->completed_at !== null)->count();

            return $completedCount / $steps->count() * 100;
        }

        // Fallback: query for completed steps
        $completedStepUsers = $this->completedStepIdsForUser($steps, $userId, $evaluationPrimaryCourseId);

        return $completedStepUsers->count() / $steps->count() * 100;
    }

    private function getEmbeddedPlayerCompletionPercentageForUser(int|string $userId): float
    {
        $lessons = $this->relationLoaded('lessons')
            ? $this->lessons
            : $this->lessons()->with('steps')->get();

        // Only lessons with non-test steps are trackable; test-only lessons are never
        // marked complete and must not inflate the denominator below 100%.
        $lastTrackableSteps = $lessons
            ->map(function (Lesson $lesson): ?Step {
                $steps = $lesson->relationLoaded('steps')
                    ? $lesson->steps
                    : $lesson->steps()->get();

                $eligibleSteps = $steps
                    ->filter(fn (Step $step): bool => $step->material_type !== 'test')
                    ->sortBy('order')
                    ->values();

                return $eligibleSteps->isEmpty() ? null : $eligibleSteps->last();
            })
            ->filter();

        if ($lastTrackableSteps->isEmpty()) {
            return 0;
        }

        $completedLessons = StepUser::query()
            ->where('user_id', $userId)
            ->whereIn('step_id', $lastTrackableSteps->pluck('id'))
            ->whereNotNull('completed_at')
            ->count();

        return ($completedLessons / $lastTrackableSteps->count()) * 100;
    }

    public function certificateUrl(): string
    {
        return CourseCompleted::getUrl([$this->slug]);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getMediaUrl('courses');
    }

    // Add the users() relationship for the pivot table
    public function users(): BelongsToMany
    {
        $userModel = config('filament-lms.user_model');

        return $this->belongsToMany($userModel, 'lms_course_user', 'course_id', 'user_id')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    /**
     * The authenticated user's row on lms_course_user (0 or 1). Eager-load on course lists (e.g. dashboard)
     * so {@see getCompletedAtAttribute} does not run a pivot query per course.
     */
    public function authEnrollment(): BelongsToMany
    {
        $userModel = config('filament-lms.user_model');

        return $this->belongsToMany($userModel, 'lms_course_user', 'course_id', 'user_id')
            ->withPivot('completed_at')
            ->withTimestamps()
            ->where('lms_course_user.user_id', Auth::id());
    }

    private function formatPivotCompletedAt(?Pivot $pivot): ?string
    {
        if ($pivot === null) {
            return null;
        }

        $at = $pivot->getAttribute('completed_at');

        if ($at === null) {
            return null;
        }

        return $at instanceof DateTimeInterface ? $at->format('Y-m-d H:i:s') : (string) $at;
    }

    public function courseCreditCategories(): HasMany
    {
        return $this->hasMany(CourseCreditCategory::class);
    }

    /**
     * Check if all steps are completed by the user (regardless of test percentage requirement).
     * This is useful for determining if a user has finished all steps but may not have met the test percentage requirement.
     */
    public function allStepsCompletedByUser(int|string $userId, ?int $evaluationPrimaryCourseId = null): bool
    {
        $steps = $this->steps()->get();

        if ($steps->isEmpty()) {
            return false;
        }

        $completedStepIds = $this->completedStepIdsForUser($steps, $userId, $evaluationPrimaryCourseId);

        return $completedStepIds->count() === $steps->count();
    }

    /**
     * Check if a user can access this course based on private status and user assignments.
     */
    public function canBeAccessedBy($user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isEvaluationCourse()) {
            if ($user->isLmsAdmin()) {
                return true;
            }

            return app(CourseEvaluationService::class)->isEvaluationUnlockedForUser($this, $user)
                && ($this->users()->where('user_id', $user->id)->exists() || ! $this->is_private);
        }

        // Public courses (not private) - accessible to everyone
        if (! $this->is_private) {
            return true;
        }

        // Private courses - only accessible to LMS admins or assigned users
        if ($user->isLmsAdmin()) {
            return true;
        }

        // Check if user is assigned to this course
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Overall test percentage for the user across all test steps in this course (0–100).
     * Steps with no entry or grading errors count as 0. Returns 0 if there are no test steps.
     */
    public function getOverallTestPercentageForUser(int|string $userId): float
    {
        $testSteps = $this->getOrderedTestSteps();

        if ($testSteps->isEmpty()) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($testSteps as $step) {
            $sum += $this->getTestStepPercentageForUser($step, $userId);
        }

        return round($sum / $testSteps->count(), 2);
    }

    /**
     * First test step (in course order) where the user scored below 100%, or null if all are 100%.
     */
    public function getFirstTestStepBelowPerfectForUser(int|string $userId): ?Step
    {
        $testSteps = $this->getOrderedTestSteps();

        foreach ($testSteps as $step) {
            $pct = $this->getTestStepPercentageForUser($step, $userId);
            if ($pct < 100) {
                return $step;
            }
        }

        return null;
    }

    /**
     * URL to the first test step below 100% for the user, or the dashboard URL if none.
     */
    public function getUrlToFirstTestStepBelowPerfectForUser(int|string $userId): string
    {
        $step = $this->getFirstTestStepBelowPerfectForUser($userId);

        return $step ? StepPage::getUrlForStep($step) : Dashboard::getUrl();
    }

    /**
     * Get all test steps for this course in lesson/step order.
     *
     * @return Collection<int, Step>
     */
    public function getOrderedTestSteps(): Collection
    {
        return $this->lessons()
            ->with(['steps' => fn ($q) => $q->where('material_type', 'test')->orderBy('order')])
            ->orderBy('order')
            ->get()
            ->pluck('steps')
            ->flatten();
    }

    protected static function newFactory()
    {
        return CourseFactory::new();
    }

    /**
     * Get the percentage score for a single test step for a user (0–100), or 0 if no entry or on error.
     */
    protected function getTestStepPercentageForUser(Step $step, int|string $userId): float
    {
        $step->load('material');
        $test = $step->material;

        if (! $test instanceof Test) {
            return 0.0;
        }

        $entry = FilamentFormUser::where('filament_form_id', $test->filament_form_id)
            ->where('user_id', $userId)
            ->when($test->filament_form_user_id, fn ($q) => $q->where('id', '!=', $test->filament_form_user_id))
            ->first();

        if (! $entry) {
            return 0.0;
        }

        $grade = $test->gradeEntry($entry);

        if ($grade instanceof Exception) {
            return 0.0;
        }

        return (float) $grade;
    }

    /**
     * @param  Collection<int, Step>  $steps
     * @return Collection<int, int>
     */
    private function completedStepIdsForUser(Collection $steps, int|string $userId, ?int $evaluationPrimaryCourseId = null): Collection
    {
        return StepUser::whereIn('step_id', $steps->pluck('id'))
            ->where('user_id', $userId)
            ->when(
                $evaluationPrimaryCourseId !== null,
                fn ($query) => $query->where('evaluation_primary_course_id', $evaluationPrimaryCourseId),
                fn ($query) => $query->whereNull('evaluation_primary_course_id'),
            )
            ->whereNotNull('completed_at')
            ->pluck('step_id')
            ->unique()
            ->values();
    }

    private function resolveEvaluationPrimaryCourseIdForCurrentUser(?int $evaluationPrimaryCourseId): ?int
    {
        if (! $this->isEvaluationCourse() || ! Auth::check()) {
            return null;
        }

        if ($evaluationPrimaryCourseId !== null) {
            return $evaluationPrimaryCourseId;
        }

        $user = Auth::user();

        if (! $user instanceof Authenticatable || (method_exists($user, 'isLmsAdmin') && $user->isLmsAdmin())) {
            return null;
        }

        $evaluationService = app(CourseEvaluationService::class);
        $primaryCourse = $evaluationService->activePrimaryCourseForEvaluation($this, $user);

        if ($primaryCourse === null || $evaluationService->evaluationCompletedByUser($primaryCourse, $user->id)) {
            return null;
        }

        return $primaryCourse->id;
    }
}
