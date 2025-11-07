<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Database\Factories\CourseFactory;
use Tapp\FilamentLms\Pages\CourseCompleted;
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
 * @property bool $is_private
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|Lesson[] $lessons
 * @property-read \Illuminate\Database\Eloquent\Collection|Step[] $steps
 */
class Course extends Model implements HasMedia
{
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
        });
    }

    protected static function newFactory()
    {
        return CourseFactory::new();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->ordered();
    }

    public function linkToCurrentStep(): string
    {
        // Get all steps in order
        $allSteps = $this->steps()->ordered()->get();

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

        return $firstIncompleteStep ? StepPage::getUrl([$firstIncompleteStep->lesson->course->slug, $firstIncompleteStep->lesson->slug, $firstIncompleteStep->slug]) : '';
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
        return $this->hasManyThrough(Step::class, Lesson::class)->orderBy('lms_steps.order');
    }

    public function startedByUserAt($userId): ?string
    {
        return StepUser::whereIn('step_id', $this->steps()->pluck('lms_steps.id'))
            ->where('user_id', $userId)
            ->min('created_at');
    }

    public function completedByUserAt($userId): ?string
    {
        // Get all steps for this course
        $steps = $this->steps()->get();

        if ($steps->isEmpty()) {
            return null;
        }

        // Get all completed steps for this specific user
        $completedStepUsers = StepUser::whereIn('step_id', $steps->pluck('id'))
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->get();

        // Check if all steps are completed
        if ($completedStepUsers->count() === $steps->count()) {
            return $completedStepUsers->max('completed_at');
        }

        return null;
    }

    public function getCompletedAtAttribute()
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
        // Get all steps for this course
        $steps = $this->steps()->get();

        if ($steps->isEmpty()) {
            return 0;
        }

        // Get all completed steps for this specific user
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

    public function getImageUrlAttribute()
    {
        $mediaUrl = $this->getMediaUrl('courses');

        return $mediaUrl ?: 'https://picsum.photos/200';
    }

    // Add the users() relationship for the pivot table
    public function users()
    {
        $userModel = config('filament-lms.user_model');

        return $this->belongsToMany($userModel, 'lms_course_user', 'course_id', 'user_id')->withTimestamps();
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
}
