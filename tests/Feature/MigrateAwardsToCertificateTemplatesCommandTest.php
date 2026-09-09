<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Services\MigrateAwardsToCertificateTemplates;
use Tapp\FilamentLms\Tests\Fakes\FakeCertificateLayout;
use Tapp\FilamentLms\Tests\Fakes\FakeCertificateTemplate;

function publishAwardFixture(string $award, string $fixture): void
{
    $path = resource_path('views/vendor/filament-lms/certificates/'.$award.'.blade.php');

    File::ensureDirectoryExists(dirname($path));
    File::copy(__DIR__.'/../fixtures/certificates/'.$fixture, $path);
}

function seedAwardLogo(string $relativePath): string
{
    $absolute = public_path(ltrim($relativePath, '/'));

    File::ensureDirectoryExists(dirname($absolute));
    File::put($absolute, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return $absolute;
}

it('fails when the certificate builder is not enabled', function () {
    $this->artisan('filament-lms:migrate-awards-to-templates')
        ->expectsOutputToContain('Certificate builder is not enabled')
        ->assertFailed();
});

it('creates a template per award and assigns untemplated courses', function () {
    config([
        'filament-lms.awards' => [
            'default' => 'Default',
            'decan' => 'Delaware Contraceptive Access Network',
        ],
    ]);

    publishAwardFixture('decan', 'custom-award.blade.php');
    seedAwardLogo('img/DE_DHSS-logo-red-wide.png');

    $defaultCourse = Course::factory()->create([
        'name' => 'Default Award Course',
        'external_id' => 'default_award_course',
        'award' => 'default',
        'certificate_template_id' => null,
    ]);
    $decanCourse = Course::factory()->create([
        'name' => 'Decan Award Course',
        'external_id' => 'decan_award_course',
        'award' => 'decan',
        'certificate_template_id' => null,
    ]);
    $alreadyAssigned = Course::factory()->create([
        'name' => 'Already Assigned Course',
        'external_id' => 'already_assigned_course',
        'award' => 'decan',
        'certificate_template_id' => 99,
    ]);

    $summary = app(MigrateAwardsToCertificateTemplates::class)->handle(
        templateClass: FakeCertificateTemplate::class,
        layoutClass: FakeCertificateLayout::class,
    );

    $defaultTemplate = FakeCertificateTemplate::query()->where('name', 'Default Certificate')->first();
    $decanTemplate = FakeCertificateTemplate::query()
        ->where('name', 'Delaware Contraceptive Access Network Certificate')
        ->first();

    expect($summary['templates_created'])->toBe(2)
        ->and($summary['courses_updated'])->toBe(2)
        ->and($defaultTemplate)->not->toBeNull()
        ->and($decanTemplate)->not->toBeNull()
        ->and($decanTemplate->token_set)->toBe('course')
        ->and($decanTemplate->layout['elements'])->toContain(
            ['id' => 'certifying_line', 'type' => 'text', 'text' => 'AWARDED TO:', 'visible' => true],
        )
        ->and($decanTemplate->getFirstMedia('logo_1'))->not->toBeNull()
        ->and($defaultCourse->refresh()->certificate_template_id)->toBe($defaultTemplate->id)
        ->and($decanCourse->refresh()->certificate_template_id)->toBe($decanTemplate->id)
        ->and($alreadyAssigned->refresh()->certificate_template_id)->toBe(99);
});

it('is idempotent', function () {
    config(['filament-lms.awards' => [
        'decan' => 'Delaware Contraceptive Access Network',
    ]]);

    publishAwardFixture('decan', 'custom-award.blade.php');

    Course::factory()->create([
        'name' => 'Decan Course',
        'external_id' => 'decan_idempotent',
        'award' => 'decan',
    ]);

    $migrator = app(MigrateAwardsToCertificateTemplates::class);

    $migrator->handle(
        templateClass: FakeCertificateTemplate::class,
        layoutClass: FakeCertificateLayout::class,
    );
    $second = $migrator->handle(
        templateClass: FakeCertificateTemplate::class,
        layoutClass: FakeCertificateLayout::class,
    );

    expect(FakeCertificateTemplate::query()->count())->toBe(1)
        ->and($second['templates_created'])->toBe(0)
        ->and($second['templates_reused'])->toBe(1)
        ->and($second['courses_updated'])->toBe(0);
});

it('does not write during a dry run', function () {
    config(['filament-lms.awards' => [
        'decan' => 'Delaware Contraceptive Access Network',
    ]]);

    $course = Course::factory()->create([
        'name' => 'Dry Run Course',
        'external_id' => 'dry_run_course',
        'award' => 'decan',
    ]);

    $summary = app(MigrateAwardsToCertificateTemplates::class)->handle(
        dryRun: true,
        templateClass: FakeCertificateTemplate::class,
        layoutClass: FakeCertificateLayout::class,
    );

    expect($summary['templates_created'])->toBe(1)
        ->and($summary['courses_updated'])->toBe(1)
        ->and(FakeCertificateTemplate::query()->count())->toBe(0)
        ->and($course->refresh()->certificate_template_id)->toBeNull();
});

it('can migrate a single award key', function () {
    config(['filament-lms.awards' => [
        'default' => 'Default',
        'decan' => 'Delaware Contraceptive Access Network',
    ]]);

    Course::factory()->create([
        'name' => 'Default Only Course',
        'external_id' => 'default_only_course',
        'award' => 'default',
    ]);
    $decanCourse = Course::factory()->create([
        'name' => 'Decan Only Course',
        'external_id' => 'decan_only_course',
        'award' => 'decan',
    ]);

    $summary = app(MigrateAwardsToCertificateTemplates::class)->handle(
        award: 'decan',
        templateClass: FakeCertificateTemplate::class,
        layoutClass: FakeCertificateLayout::class,
    );

    expect($summary['awards'])->toHaveCount(1)
        ->and($summary['awards'][0]['award'])->toBe('decan')
        ->and(FakeCertificateTemplate::query()->count())->toBe(1)
        ->and($decanCourse->refresh()->certificate_template_id)->not->toBeNull();
});

it('reassigns existing templates when forced', function () {
    config(['filament-lms.awards' => [
        'decan' => 'Delaware Contraceptive Access Network',
    ]]);

    publishAwardFixture('decan', 'custom-award.blade.php');

    $existing = FakeCertificateTemplate::query()->create([
        'name' => 'Delaware Contraceptive Access Network Certificate',
        'token_set' => 'training',
        'layout' => ['elements' => []],
    ]);

    $course = Course::factory()->create([
        'name' => 'Force Reassign Course',
        'external_id' => 'force_reassign_course',
        'award' => 'decan',
        'certificate_template_id' => 99,
    ]);

    app(MigrateAwardsToCertificateTemplates::class)->handle(
        force: true,
        templateClass: FakeCertificateTemplate::class,
        layoutClass: FakeCertificateLayout::class,
    );

    expect(FakeCertificateTemplate::query()->count())->toBe(1)
        ->and($existing->refresh()->token_set)->toBe('course')
        ->and($existing->layout['elements'])->not->toBeEmpty()
        ->and($course->refresh()->certificate_template_id)->toBe($existing->id);
});
