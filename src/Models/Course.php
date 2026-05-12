<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Database\Factories\CourseFactory;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step as StepPage;
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
 * @property Carbon $created_at
 * @property Carbon $updated_at
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
            ->whereHas('steps');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->ordered();
    }

    public function linkToCurrentStep(): string
    {
        // Get all steps in order
        $allSteps = $this->steps()->ordered()->get();

        // If course has no steps, return dashboard URL
        if ($allSteps->isEmpty()) {
            return Dashboard::getUrl();
        }

        // Get all completed steps for this user
        $completedStepIds = StepUser::whereIn('step_id', $allSteps->pluck('id'))
            ->where('user_id', Auth::user()->id)
            ->whereNotNull('completed_at')
            ->pluck('step_id')
            ->toArray();

        // Find the first step that hasn't been completed
        $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
            // @phpstan-ignore-next-line
            $user = Auth::user();
            if (! $user instanceof FilamentLmsUserInterface) {
                return false;
            }

            return ! in_array($step->id, $completedStepIds) && $user->canAccessStep($step);
        });

        // If no incomplete step is available, check if course is complete
        if (! $firstIncompleteStep) {
            // @phpstan-ignore-next-line
            if ($allSteps->every->completed_at) {
                return $this->certificateUrl();
            }
            // If course is not complete but no step is available, find the first incomplete step
            $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
                return ! in_array($step->id, $completedStepIds);
            });
        }

        return $firstIncompleteStep ? StepPage::getUrl([$firstIncompleteStep->lesson->course->slug, $firstIncompleteStep->lesson->slug, $firstIncompleteStep->slug]) : Dashboard::getUrl();
    }

    public function currentStep(?Authenticatable $user = null): ?Step
    {
        $user = $user ?: Auth::user();
        if (! $user) {
            return null;
        }
        $allSteps = $this->steps;

        // Get all completed steps for this user
        $completedStepIds = StepUser::whereIn('step_id', $allSteps->pluck('id'))
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->pluck('step_id')
            ->toArray();

        // Find the first step that hasn't been completed
        $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
            return ! in_array($step->id, $completedStepIds);
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
        $pivot = $this->users()->where('user_id', $userId)->first()?->getRelationValue('pivot');
        $existing = $pivot instanceof Pivot ? $pivot->getAttribute('completed_at') : null;
        if ($existing !== null) {
            return;
        }

        if (! $this->allStepsCompletedByUser($userId)) {
            return;
        }

        if ($this->required_test_percentage !== null) {
            $testSteps = $this->getOrderedTestSteps();
            if ($testSteps->isNotEmpty()) {
                $overall = $this->getOverallTestPercentageForUser($userId);
                if ($overall < (float) $this->required_test_percentage) {
                    return;
                }
            }
        }

        $this->users()->syncWithoutDetaching([$userId => ['completed_at' => now()]]);
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

        return $this->getCompletionPercentageForUser(Auth::id());
    }

    public function getCompletionPercentageForUser($userId): float
    {
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
            && $steps->first()->relationLoaded('progress')
        ) {
            $completedCount = $steps->filter(fn (Step $step) => $step->progress?->completed_at !== null)->count();

            return $completedCount / $steps->count() * 100;
        }

        // Fallback: query for completed steps
        $completedStepUsers = StepUser::whereIn('step_id', $steps->pluck('id'))
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->get();

        return $completedStepUsers->count() / $steps->count() * 100;
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
    public function allStepsCompletedByUser(int|string $userId): bool
    {
        $steps = $this->steps()->get();

        if ($steps->isEmpty()) {
            return false;
        }

        $completedStepUsers = StepUser::whereIn('step_id', $steps->pluck('id'))
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->get();

        return $completedStepUsers->count() === $steps->count();
    }

    /**
     * Check if a user can access this course based on private status and user assignments.
     */
    public function canBeAccessedBy($user): bool
    {
        if (! $user) {
            return false;
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
}
