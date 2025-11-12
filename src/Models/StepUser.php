<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;

/**
 * @property string|null $completed_at
 * @property int|null $seconds
 */
class StepUser extends Pivot
{
    use BelongsToTenant;
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
        return $this->belongsTo(config('auth.providers.users.model', Authenticatable::class));
    }
}
