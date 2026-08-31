<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tapp\FilamentLms\Models\UserGroup;

/**
 * @extends Factory<UserGroup>
 */
class UserGroupFactory extends Factory
{
    protected $model = UserGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'rules' => [
                'version' => 1,
                'sources' => [],
            ],
            'rules_version' => 1,
            'published_revision' => 0,
            'is_active' => true,
            'sync_status' => 'idle',
            'sync_error' => null,
            'last_synced_at' => null,
        ];
    }
}
