<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tapp\FilamentLms\Events\CourseCompleted;
use Tapp\FilamentLms\Events\CourseStarted;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Step extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_steps';

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function material()
    {
        return $this->morphTo();
    }

    public function complete(User $user = null)
    {
        $user = $user ?: auth()->user();

        $userStep = StepUser::where('user_id', $user->id)
            ->where('step_id', $this->id)
            ->first();

        $nextStep = $this->next_step;

        if (! $userStep) {
            // update progress
            StepUser::create([
                'user_id' => $user->id,
                'step_id' => $this->id,
                'completed_at' => now(),
            ]);

            if ($this->first_step) {
                CourseStarted::dispatch($user, $this->lesson->course);
            }
        } elseif (! $userStep->completed_at) {
            // a step may have been started but not completed (e.g. video paused)
            $userStep->update([
                'completed_at' => now(),
            ]);
        }

        if ($nextStep) {
            StepUser::firstOrCreate([
                'user_id' => $user->id,
                'step_id' => $nextStep->id,
            ]);

            return $nextStep;
        } else {
            CourseCompleted::dispatch($user, $this->lesson->course);
        }
    }

    public function getNextStepAttribute()
    {
        $nextInLesson = $this->lesson->steps()->where('order', '>', $this->order)->first();

        if ($nextInLesson) {
            return $nextInLesson;
        }

        $nextLesson = $this->lesson->course->lessons()->where('order', '>', $this->lesson->order)->first();

        return $nextLesson ? $nextLesson->steps()->first() : null;
    }

    /**
     * returns true if last step in last lesson of the course
     */
    public function getLastStepAttribute()
    {
        return $this->lesson->steps->last()->is($this) && $this->lesson->course->lessons->last()->is($this->lesson);
    }

    /**
     * returns true if last step in last lesson of the course
     */
    public function getFirstStepAttribute()
    {
        return $this->lesson->steps->first()->is($this) && $this->lesson->course->lessons->first()->is($this->lesson);
    }

    public function isActive()
    {
        return request()->is('*' . $this->slug . '*');
    }

    public function getUrlAttribute()
    {
        return StepPage::getUrlForStep($this);
    }

    /**
     * The progress for current user
     */
    public function progress(): HasOne
    {
        $currentUserId = auth()->check() ? auth()->user()->id : null;

        return $this->hasOne(StepUser::class)->ofMany([
            // TODO is this started_at => max needed?
            'created_at' => 'max',
        ], function ($query) use ($currentUserId) {
            $query->where('user_id', '=', $currentUserId);
        });
    }

    public function getCompletedAtAttribute()
    {
        return $this->progress?->completed_at;
    }

    public function getAvailableAttribute()
    {
        if (!auth()->check()) {
            return false;
        }

        return $this->completed_at || $this->lesson->course->currentStep()->order >= $this->order;
    }
}
