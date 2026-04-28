<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\CreditCategory;

class CreditCategoryFactory extends Factory
{
    protected $model = CreditCategory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'color' => $this->faker->randomElement(array_keys(CreditCategory::COLORS)),
        ];
    }
}
