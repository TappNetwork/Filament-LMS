<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Tapp\FilamentCertificateBuilder\Filament\Resources\CertificateTemplates\CertificateTemplateResource;
use Tapp\FilamentCertificateBuilder\Models\CertificateTemplate;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\CertificateBuilder;
use Tapp\FilamentLms\Tests\TestUser;

it('is enabled when the builder package is installed', function () {
    expect(class_exists(CertificateBuilder::TEMPLATE_MODEL))->toBeTrue()
        ->and(CertificateBuilder::enabled())->toBeTrue();
});

it('allows creating a template when the builder is enabled and the user can update the course', function () {
    $user = TestUser::query()->create([
        'name' => 'Admin',
        'email' => 'admin-cert-builder@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->withoutCertificateTemplate()->create();

    expect(CertificateBuilder::canCreateTemplate($user, $course))->toBeTrue()
        ->and(CertificateBuilder::canEditTemplate($user, $course))->toBeFalse();
});

it('does not allow creating a template when the user cannot update the course', function () {
    Gate::policy(Course::class, CertificateBuilderCourseUpdatePolicy::class);

    $denied = TestUser::query()->create([
        'name' => 'Denied',
        'email' => 'denied-create-template@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->withoutCertificateTemplate()->create();

    expect(CertificateBuilder::canCreateTemplate($denied, $course))->toBeFalse()
        ->and(CertificateBuilder::canEditTemplate($denied, $course))->toBeFalse();
});

it('allows course updates when no policy is registered', function () {
    $user = TestUser::query()->create([
        'name' => 'Admin',
        'email' => 'admin-no-policy@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    expect(CertificateBuilder::userCanUpdateCourse(null, $course))->toBeFalse()
        ->and(CertificateBuilder::userCanUpdateCourse($user, $course))->toBeTrue();
});

it('defers to a course policy when one exists', function () {
    Gate::policy(Course::class, CertificateBuilderCourseUpdatePolicy::class);

    $denied = TestUser::query()->create([
        'name' => 'Denied',
        'email' => 'denied-course-update@example.com',
        'password' => bcrypt('password'),
    ]);
    $allowed = TestUser::query()->create([
        'name' => 'Allowed',
        'email' => 'allowed@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    expect(CertificateBuilder::userCanUpdateCourse($denied, $course))->toBeFalse()
        ->and(CertificateBuilder::userCanUpdateCourse($allowed, $course))->toBeTrue();
});

it('allows course updates when a policy exists without an update method', function () {
    Gate::policy(Course::class, CertificateBuilderCourseViewPolicy::class);

    $user = TestUser::query()->create([
        'name' => 'Viewer',
        'email' => 'viewer-no-update@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    expect(CertificateBuilder::userCanUpdateCourse($user, $course))->toBeTrue();
});

it('returns the configured template resource when the class exists', function () {
    expect(CertificateBuilder::templateResource())->toBe(CertificateTemplateResource::class);
});

it('returns null for the template resource when the configured class is missing', function () {
    config(['filament-lms.integrations.certificate_builder.template_resource' => 'App\\Missing\\CertificateTemplateResource']);

    expect(CertificateBuilder::templateResource())->toBeNull();
});

it('uses the configured token set and falls back to course', function () {
    expect(CertificateBuilder::tokenSet())->toBe('course');

    config(['filament-lms.integrations.certificate_builder.token_set' => 'training']);

    expect(CertificateBuilder::tokenSet())->toBe('training');

    config(['filament-lms.integrations.certificate_builder.token_set' => '']);

    expect(CertificateBuilder::tokenSet())->toBe('course');
});

it('creates a default certificate template when none exist', function () {
    expect(CertificateTemplate::query()->count())->toBe(0);

    $id = CertificateBuilder::defaultTemplateId();

    expect($id)->not->toBeNull()
        ->and(CertificateTemplate::query()->whereKey($id)->value('name'))->toBe('Default Certificate')
        ->and(CertificateTemplate::query()->whereKey($id)->value('token_set'))->toBe('course');
});

class CertificateBuilderCourseUpdatePolicy
{
    public function update(object $user, Course $course): bool
    {
        return $user->email === 'allowed@example.com';
    }
}

class CertificateBuilderCourseViewPolicy
{
    public function view(object $user, Course $course): bool
    {
        return true;
    }
}
