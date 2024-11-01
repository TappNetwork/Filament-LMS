<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StepUser extends Pivot
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_step_user';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(Step::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
