<?php

namespace Tapp\FilamentLms\Database\Factories;

use Tapp\FilamentLms\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Video::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'url' => "https://player.vimeo.com/video/226053498",
        ];
    }
}
