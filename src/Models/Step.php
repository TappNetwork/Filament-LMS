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

/**
 * @property string $slug
 * @property int $order
 * @property string|null $completed_at
 * @property-read Lesson $lesson
 * @property-read StepUser|null $progress
 */
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
        // @phpstan-ignore-next-line
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
        } else {
            // step is already completed
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
        return request()->is('*'.$this->lesson->course->slug.'/'.$this->lesson->slug.'/'.$this->slug.'*');
    }

    public function getUrlAttribute()
    {
        return StepPage::getUrlForStep($this);
    }

    public function videoProgress(int $seconds): void
    {
        // @phpstan-ignore-next-line
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

            if ($this->first_step) {
                CourseStarted::dispatch($user, $this->lesson->course);
            }
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
        // @phpstan-ignore-next-line
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
        // @phpstan-ignore-next-line
        if (! auth()->check()) {
            return false;
        }

        // If step is already completed, it's available
        if ($this->completed_at) {
            return true;
        }

        // Get all steps in the course up to this step
        $previousSteps = $this->lesson->course->steps()
            ->where(function ($query) {
                $query->where('lms_lessons.order', '<', $this->lesson->order)
                    ->orWhere(function ($query) {
                        $query->where('lms_lessons.order', '=', $this->lesson->order)
                            ->where('lms_steps.order', '<', $this->order);
                    });
            })
            ->with('progress')
            ->get();

        return $previousSteps->every(fn ($step) => $step->completed_at !== null);
    }

    public function getSecondsAttribute()
    {
        return $this->progress?->seconds ?? 0;
    }
}
