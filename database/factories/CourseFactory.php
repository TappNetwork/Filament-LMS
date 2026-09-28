<?php

namespace Tapp\FilamentLms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\CertificateBuilder;

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
        $externalId = Str::slug($name, '_');

        return [
            'name' => $name,
            'slug' => $slug,
            'external_id' => $externalId,
            'certificate_template_id' => CertificateBuilder::defaultTemplateId(),
            'description' => $this->faker->sentence(),
        ];
    }

    public function withoutCertificateTemplate(): static
    {
        return $this->state([
            'certificate_template_id' => null,
        ]);
    }
}
