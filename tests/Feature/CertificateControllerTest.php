<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tapp\FilamentCertificateBuilder\Actions\ResolveSampleCertificateTokensAction;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\CertificateBuilder;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config([
        'filament-lms.user_model' => TestUser::class,
        'auth.providers.users.model' => TestUser::class,
        'certificate-builder.token_sets.course' => [
            'label' => 'Course',
            'resolver' => ResolveSampleCertificateTokensAction::class,
            'tokens' => [
                'recipient_name' => [
                    'label' => 'Recipient name',
                    'sample' => 'Jane Doe',
                ],
                'course_name' => [
                    'label' => 'Course name',
                    'sample' => 'Sample Course',
                ],
                'date_range' => [
                    'label' => 'Date range',
                    'sample' => 'January 15th - March 15th 2026',
                ],
            ],
            'default_copy' => [
                'certifying_line' => 'This certifies that',
                'completed_line' => 'has successfully completed',
                'description' => '',
            ],
        ],
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

it('redirects guests away from the certificate show page', function () {
    Route::get('/login', fn () => 'login')->name('login');

    $user = TestUser::query()->create([
        'name' => 'Guest Target',
        'email' => 'guest-target-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'name' => 'Guest Blocked Course',
    ]);

    $course->users()->attach($user->id, ['completed_at' => now()]);

    $this->get(route('filament-lms::certificates.show', ['course' => $course->id, 'user' => $user->id]))
        ->assertRedirect(route('login'));
});

it('allows the certificate owner to view the HTML certificate', function () {
    $user = TestUser::query()->create([
        'name' => 'Owner Learner',
        'email' => 'owner-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'name' => 'Owner Course',
    ]);

    $course->users()->attach($user->id, ['completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('filament-lms::certificates.show', ['course' => $course->id, 'user' => $user->id]))
        ->assertOk();
});

it('allows an admin who can update the course to preview another users certificate', function () {
    Gate::policy(Course::class, CertificateShowCourseUpdatePolicy::class);

    $learner = TestUser::query()->create([
        'name' => 'Learner',
        'email' => 'learner-cert@example.com',
        'password' => bcrypt('password'),
    ]);
    $admin = TestUser::query()->create([
        'name' => 'Allowed Admin',
        'email' => 'allowed@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'name' => 'Admin Preview Course',
    ]);

    $course->users()->attach($learner->id, ['completed_at' => now()]);

    $this->actingAs($admin)
        ->get(route('filament-lms::certificates.show', ['course' => $course->id, 'user' => $learner->id]))
        ->assertOk();
});

it('forbids another authenticated user from viewing someone elses certificate', function () {
    Gate::policy(Course::class, CertificateShowCourseUpdatePolicy::class);

    $owner = TestUser::query()->create([
        'name' => 'Certificate Owner',
        'email' => 'owner-other-cert@example.com',
        'password' => bcrypt('password'),
    ]);
    $stranger = TestUser::query()->create([
        'name' => 'Stranger',
        'email' => 'stranger-cert@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'name' => 'Private Certificate Course',
    ]);

    $course->users()->attach($owner->id, ['completed_at' => now()]);

    $this->actingAs($stranger)
        ->get(route('filament-lms::certificates.show', ['course' => $course->id, 'user' => $owner->id]))
        ->assertForbidden();
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

class CertificateShowCourseUpdatePolicy
{
    public function update(object $user, Course $course): bool
    {
        return $user->email === 'allowed@example.com';
    }
}
