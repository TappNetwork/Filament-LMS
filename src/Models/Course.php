<?php

namespace Tapp\FilamentLms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Tapp\FilamentLms\Database\Factories\CourseFactory;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Step as StepPage;

class Course extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_courses';

    protected $casts = [
        'award_content' => 'array',
    ];

    /**
     * Scope a query to only include popular users.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereHas('steps')->where('hidden', false);
    }

    protected static function newFactory()
    {
        return CourseFactory::new();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function linkToCurrentStep(): string
    {
        $step = $this->currentStep();

        if ($step && $step->completed_at && $step->last_step) {
            return $this->certificateUrl();
        }

        return $step ? StepPage::getUrl([$step->lesson->course->slug, $step->lesson->slug, $step->slug]) : '';
    }

    public function currentStep(?User $user = null): ?Step
    {
        $user = $user ?: auth()->user();

        $userStep = StepUser::whereIn('lms_step_user.step_id', $this->steps()->pluck('lms_steps.id'))
            ->join('lms_steps', 'lms_step_user.step_id', '=', 'lms_steps.id')
            ->where('lms_step_user.user_id', $user->id)
            ->orderBy('lms_steps.order', 'desc')
            ->first();

        return $userStep ? $userStep->step : $this->firstStep();
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
        if (! auth()->check()) {
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
        return $this->image ? url($this->image) : 'https://picsum.photos/200';
    }
}
