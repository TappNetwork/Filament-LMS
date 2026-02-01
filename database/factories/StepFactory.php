<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
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

        return [
            'name' => $name,
            'lesson_id' => Lesson::factory()->lazy(),
            'slug' => function (array $attributes) {
                $lessonId = $attributes['lesson_id'] ?? null;
                $stepName = $attributes['name'] ?? $this->faker->unique()->word();

                $slug = Step::generateSlug($lessonId, $stepName);

                return $slug ?? Str::slug($stepName);
            },
            'order' => 1,
            'material_type' => 'video',
            'material_id' => Video::factory()->lazy(),
        ];
    }

    /**
     * Indicate that the step should not have any material.
     */
    public function withoutMaterial(): static
    {
        return $this->state(fn () => [
            'material_type' => null,
            'material_id' => null,
        ]);
    }

    /**
     * Indicate that the step should have text content.
     */
    public function withText(?string $text = null): static
    {
        return $this->state(fn (array $attributes) => [
            'text' => $text ?? $this->faker->paragraphs(3, true),
        ]);
    }
}
