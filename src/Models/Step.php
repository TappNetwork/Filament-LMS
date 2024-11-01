<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tapp\FilamentLms\Events\CourseCompleted;
use Tapp\FilamentLms\Events\CourseStarted;

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
            StepUser::create([
                'user_id' => $user->id,
                'step_id' => $nextStep->id,
                'completed_at' => now(),
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
}
