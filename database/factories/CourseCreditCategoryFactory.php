<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CourseCreditCategory;
use Tapp\FilamentLms\Models\CreditCategory;

class CourseCreditCategoryFactory extends Factory
{
    protected $model = CourseCreditCategory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'credit_category_id' => CreditCategory::factory(),
            'credits' => $this->faker->randomFloat(2, 0.5, 10),
        ];
    }
}
