<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Pages\CourseCompleted;

class Course extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_courses';

    protected $casts = [
        'award_content' => 'array',
    ];

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function linkToCurrentStep(): string
    {
        $step = $this->currentStep();

        return $step ? StepPage::getUrl([$step->lesson->course->slug, $step->lesson->slug, $step->slug]) : '';
    }

    public function currentStep(?User $user = null): ?Step
    {
        $user = $user ?: auth()->user();

        $userStep = StepUser::whereIn('lms_step_user.step_id', $this->steps()->pluck('lms_steps.id'))
            ->join('lms_steps', 'lms_step_user.step_id', '=', 'lms_steps.id')
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
        $this->loadProgress();

        $completedSteps = $this->steps->filter->completed_at;

        return $completedSteps->count() / $this->steps->count() * 100;
    }

    public function certificateUrl(): string
    {
        return CourseCompleted::getUrl([$this->slug]);
    }
}
