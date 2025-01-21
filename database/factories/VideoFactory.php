<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tapp\FilamentLms\Models\Video;

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
            'url' => 'https://www.youtube.com/watch?v=1wdqeyD-5YM',
            // 'url' => 'https://player.vimeo.com/video/226053498',
        ];
    }
}
