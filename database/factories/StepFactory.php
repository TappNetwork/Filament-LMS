<?php

namespace Tapp\FilamentLms\Database\Factories;

use Tapp\FilamentLms\Models\Step;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Video;

class StepFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Step::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        $slug = Str::slug($name);

        return [
            'name' => $name,
            'slug' => $slug,
            'lesson_id' => Lesson::factory()->lazy(),
            'order' => 1,
            'material_type' => 'video',
            'material_id' => Video::factory()->lazy(),
        ];
    }
}
