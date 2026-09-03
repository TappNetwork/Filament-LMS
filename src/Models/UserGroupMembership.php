<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_group_id
 * @property int $user_id
 * @property int $revision
 * @property Carbon|null $matched_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read UserGroup $userGroup
 */
class UserGroupMembership extends Model
{
    protected $guarded = [];

    protected $table = 'lms_user_group_memberships';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'matched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UserGroup, $this>
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }

    public function user(): BelongsTo
    {
        $userModel = config('filament-lms.user_model');

        return $this->belongsTo($userModel, 'user_id');
    }
}
