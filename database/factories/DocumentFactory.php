<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tapp\FilamentLms\Models\Document;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
        ];
    }

    /**
     * Associate media after creating
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($document) {
            /** @var Document $document */
            $testFile = './vendor/tapp/filament-lms/test.pdf';
            $document->addMedia($testFile)
                ->preservingOriginal()
                ->toMediaCollection();
        });
    }
}
