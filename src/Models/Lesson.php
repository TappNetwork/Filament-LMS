<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Tapp\FilamentLms\Database\Factories\LessonFactory;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;

/**
 * @property int $id
 * @property int $course_id
 * @property int $order
 * @property string $name
 * @property string $slug
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Course $course
 * @property-read Collection<int, Step> $steps
 */
class Lesson extends Model implements Sortable
{
    use BelongsToTenant;
    use HasFactory;
    use SortableTrait;

    public $sortable = [
        'order_column_name' => 'order',
    ];

    protected $guarded = [];

    protected $table = 'lms_lessons';

    protected static function newFactory()
    {
        return LessonFactory::new();
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function steps(): HasMany
    {
        // @phpstan-ignore-next-line
        return $this->hasMany(Step::class)->ordered();
    }

    public function isActive()
    {
        // First check if we're on a lesson URL pattern
        if (request()->is('*/'.$this->course->slug.'/'.$this->slug.'*')) {
            return true;
        }

        // Then check if any step in this lesson is currently active
        return $this->steps->contains(function (Step $step) {
            return $step->isActive();
        });
    }

    public function getCompletedAtAttribute()
    {
        $this->loadProgress();

        if ($this->steps->every(function (Step $step) {
            return $step->completed_at !== null;
        })) {
            return $this->steps->pluck('completed_at')->max();
        }

        return null;
    }

    /**
     * TODO check if already loaded
     * load progress for course and steps
     * make sure steps are in order
     **/
    public function loadProgress()
    {
        $this->load([
            'steps' => function ($query) {
                $query->orderBy('order');
            },
            'steps.progress',
        ]);
    }
}
