<?php

declare(strict_types=1);

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\CertificateBuilder;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config([
        'filament-lms.user_model' => TestUser::class,
        'auth.providers.users.model' => TestUser::class,
    ]);
});

it('returns not found when the course has no certificate template', function () {
    $user = TestUser::query()->create([
        'name' => 'Jane Doe',
        'email' => 'jane-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->withoutCertificateTemplate()->create([
        'name' => 'Intro to CHW',
    ]);

    $course->users()->attach($user->id, ['completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('filament-lms::certificates.show', ['course' => $course->id, 'user' => $user->id]))
        ->assertNotFound();
});

it('forbids download when the user has not completed the course', function () {
    $user = TestUser::query()->create([
        'name' => 'Pat Learner',
        'email' => 'pat-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->withoutCertificateTemplate()->create();

    $this->actingAs($user)
        ->get(route('filament-lms::certificates.download', $course))
        ->assertForbidden();
});

it('exposes create but not edit when the course has no certificate template', function () {
    $user = TestUser::query()->create([
        'name' => 'Pat Admin',
        'email' => 'pat-admin-cert@example.com',
        'password' => bcrypt('password'),
    ]);
    $course = Course::factory()->withoutCertificateTemplate()->create();

    expect(CertificateBuilder::enabled())->toBeTrue()
        ->and(CertificateBuilder::canCreateTemplate($user, $course))->toBeTrue()
        ->and(CertificateBuilder::canEditTemplate($user, $course))->toBeFalse();
});
