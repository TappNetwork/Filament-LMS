<?php

declare(strict_types=1);

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Support\CertificateBuilder;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config([
        'filament-lms.user_model' => TestUser::class,
        'auth.providers.users.model' => TestUser::class,
    ]);
});

it('renders the default award certificate when no template is assigned', function () {
    $user = TestUser::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'name' => 'Intro to CHW',
        'award' => 'default',
    ]);

    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->create(['lesson_id' => $lesson->id]);

    $course->users()->attach($user->id, ['completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('filament-lms::certificates.show', ['course' => $course->id, 'user' => $user->id]))
        ->assertSuccessful()
        ->assertSee('CERTIFICATE', false)
        ->assertSee('Intro to CHW', false)
        ->assertSee('Jane Doe', false);
});

it('forbids download when the user has not completed the course', function () {
    $user = TestUser::query()->create([
        'name' => 'Pat Learner',
        'email' => 'pat-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create(['award' => 'default']);

    $this->actingAs($user)
        ->get(route('filament-lms::certificates.download', $course))
        ->assertForbidden();
});

it('does not expose the create certificate template action when the builder is disabled', function () {
    config(['filament-lms.integrations.certificate_builder.enabled' => false]);

    $user = TestUser::query()->create([
        'name' => 'Pat Admin',
        'email' => 'pat-admin-cert@example.com',
        'password' => bcrypt('password'),
    ]);
    $course = Course::factory()->create(['award' => 'default']);

    expect(CertificateBuilder::enabled())->toBeFalse()
        ->and(CertificateBuilder::canCreateTemplate($user, $course))->toBeFalse()
        ->and(CertificateBuilder::canEditTemplate($user, $course))->toBeFalse();
});
