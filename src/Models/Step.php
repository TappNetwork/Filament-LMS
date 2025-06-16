<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Tapp\FilamentLms\Database\Factories\StepFactory;
use Tapp\FilamentLms\Events\CourseCompleted;
use Tapp\FilamentLms\Events\CourseStarted;
use Tapp\FilamentLms\Events\StepCompleted;
use Tapp\FilamentLms\Pages\Step as StepPage;

class Step extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    public $sortable = [
        'order_column_name' => 'order',
    ];

    protected $guarded = [];

    protected $table = 'lms_steps';

    protected static function newFactory()
    {
        return StepFactory::new();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function material(): MorphTo
    {
        return $this->morphTo();
    }

    public function complete($user = null)
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

            StepCompleted::dispatch($user, $this);
        } elseif (! $userStep->completed_at) {
            // a step may have been started but not completed (e.g. video paused)
            $userStep->update([
                'completed_at' => now(),
            ]);

            StepCompleted::dispatch($user, $this);
        }

        if ($nextStep) {
            StepUser::firstOrCreate([
                'user_id' => $user->id,
                'step_id' => $nextStep->id,
            ]);

            return $nextStep;
        } else {
            CourseCompleted::dispatch($user, $this->lesson->course);
            StepCompleted::dispatch($user, $this);
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
        return request()->is('*'.$this->lesson->course->slug.'/'.$this->lesson->slug.'/'.$this->slug.'*');
    }

    public function getUrlAttribute()
    {
        return StepPage::getUrlForStep($this);
    }

    public function videoProgress(int $seconds): void
    {
        $user = auth()->user();

        $userStep = StepUser::where('user_id', $user->id)
            ->where('step_id', $this->id)
            ->first();

        if (! $userStep) {
            StepUser::create([
                'user_id' => $user->id,
                'step_id' => $this->id,
                'seconds' => $seconds,
            ]);
        } else {
            $userStep->update([
                'seconds' => $seconds,
            ]);
        }
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
        if (! auth()->check()) {
            return false;
        }

        // If this is the first step of the first lesson, it's always available
        if ($this->first_step) {
            return true;
        }

        // If this is the first step of a lesson, check if previous lesson is complete
        if ($this->lesson->steps->first()->is($this)) {
            $previousLesson = $this->lesson->course->lessons()
                ->where('order', '<', $this->lesson->order)
                ->ordered()
                ->first();

            if (! $previousLesson) {
                return true; // No previous lesson, so this is available
            }

            // Check if all steps in previous lesson are complete
            return $previousLesson->steps->every->completed_at;
        }

        // For other steps, check if previous step is complete
        $previousStep = $this->lesson->steps()
            ->where('order', '<', $this->order)
            ->ordered()
            ->first();

        if (! $previousStep) {
            return true; // No previous step, so this is available
        }

        return $previousStep->completed_at;
    }

    public function getSecondsAttribute()
    {
        return $this->progress?->seconds ?? 0;
    }
}
