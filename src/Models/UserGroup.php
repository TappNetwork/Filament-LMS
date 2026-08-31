<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tapp\FilamentLms\Database\Factories\UserGroupFactory;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property array $rules
 * @property int $rules_version
 * @property int $published_revision
 * @property bool $is_active
 * @property string $sync_status
 * @property string|null $sync_error
 * @property Carbon|null $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserGroup extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_user_groups';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'rules_version' => 'integer',
            'published_revision' => 'integer',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserGroupFactory
    {
        return UserGroupFactory::new();
    }

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'lms_course_user_group', 'user_group_id', 'course_id')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /**
     * @return HasMany<UserGroupMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(UserGroupMembership::class);
    }

    /**
     * @return HasMany<UserGroupMembership, $this>
     */
    public function publishedMemberships(): HasMany
    {
        return $this->memberships()
            ->where('revision', $this->published_revision);
    }

    public function hasPublishedMembership(int|string $userId): bool
    {
        if ($this->published_revision < 1 || ! $this->is_active) {
            return false;
        }

        return $this->memberships()
            ->where('revision', $this->published_revision)
            ->where('user_id', $userId)
            ->exists();
    }
}
