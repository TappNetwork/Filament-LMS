<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tapp\FilamentLms\Models\Link;

class LinkFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'url' => $this->faker->url,
        ];
    }
}
