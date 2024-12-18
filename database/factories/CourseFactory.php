<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\Course;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        $slug = Str::slug($name);
        $externalId = Str::snake($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'external_id' => $externalId,
            'award_layout' => 'default',
            'description' => $this->faker->sentence(),
        ];
    }
}
