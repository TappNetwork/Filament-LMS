<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Tapp\FilamentLms\Database\Factories\LessonFactory;

class Lesson extends Model implements Sortable
{
    use HasFactory, SortableTrait;

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
        return $this->hasMany(Step::class)->orderBy('order');
    }

    public function isActive()
    {
        return request()->is('*'.$this->slug.'*');
    }

    public function getCompletedAtAttribute()
    {
        $this->loadProgress();

        if ($this->steps->every->completed_at) {
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
