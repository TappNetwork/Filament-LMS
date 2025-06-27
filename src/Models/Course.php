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
use Tapp\FilamentLms\Database\Factories\CourseFactory;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Step as StepPage;

/**
 * @property string|null $award
 * @property array $award_content
 */
class Course extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $guarded = [];

    protected $table = 'lms_courses';

    protected $casts = [
        'award_content' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('courses')
            ->singleFile();
    }

    /**
     * Scope a query to only include popular users.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereHas('steps')->where('hidden', false);

        if (config('filament-lms.restrict_course_visibility') && Auth::check()) {
            $query->whereHas('users', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }
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
            ->where('user_id', auth()->user()->id)
            ->whereNotNull('completed_at')
            ->pluck('step_id')
            ->toArray();

        // Find the first step that hasn't been completed
        $firstIncompleteStep = $allSteps->first(function ($step) use ($completedStepIds) {
            return ! in_array($step->id, $completedStepIds) && $step->available;
        });

        // If no incomplete step is available, check if course is complete
        if (! $firstIncompleteStep) {
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

    public function completedByUserAt($userId): ?string
    {
        $userSteps = StepUser::whereIn('lms_step_user.step_id', $this->steps()->pluck('lms_steps.id'))
            ->where('lms_step_user.user_id', $userId)
            ->whereNotNull('lms_step_user.completed_at')
            ->get();

        foreach ($this->steps as $step) {
            if (! $userSteps->contains('step_id', $step->id)) {
                return null;
            }
        }

        return $userSteps->max('completed_at');
    }

    public function getCompletedAtAttribute()
    {
        if (! Auth::check()) {
            return null;
        }

        if ($this->steps->every->completed_at) {
            return $this->steps->pluck('completed_at')->max();
        }

        return null;
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
        if ($this->steps->isEmpty()) {
            return 0;
        }

        $this->loadProgress();

        $completedSteps = $this->steps->filter->completed_at;

        return $completedSteps->count() / $this->steps->count() * 100;
    }

    public function certificateUrl(): string
    {
        return CourseCompleted::getUrl([$this->slug]);
    }

    public function getImageUrlAttribute()
    {
        $mediaPath = $this->getFirstMediaUrl('courses');

        return $mediaPath ?: 'https://picsum.photos/200';
    }

    // Add the users() relationship for the pivot table
    public function users()
    {
        return $this->belongsToMany(\Illuminate\Foundation\Auth\User::class, 'lms_course_user', 'course_id', 'user_id')->withTimestamps();
    }
}
