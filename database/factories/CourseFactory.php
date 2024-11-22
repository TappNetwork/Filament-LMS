<?php

namespace Tapp\FilamentLms\Database\Factories;

use Tapp\FilamentLms\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
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
            'description' => 'This course will introduce you to the basics of AI.',
        ];
    }
}
