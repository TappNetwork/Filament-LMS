<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\AwardCertificateBlueprintFactory;

function publishAwardView(string $award, string $fixture): void
{
    $path = resource_path('views/vendor/filament-lms/certificates/'.$award.'.blade.php');

    File::ensureDirectoryExists(dirname($path));
    File::copy(__DIR__.'/../fixtures/certificates/'.$fixture, $path);
}

it('discovers configured awards and awards used on courses', function () {
    config(['filament-lms.awards' => [
        'default' => 'Default',
        'safb' => 'Safe Arms For Babies',
    ]]);

    Course::factory()->create([
        'name' => 'Used Custom Award Course',
        'external_id' => 'used_custom_award',
        'award' => 'decan',
    ]);

    $keys = app(AwardCertificateBlueprintFactory::class)->awardKeys();

    expect($keys)->toBe(['decan', 'default', 'safb']);
});

it('limits discovery to an explicit award key', function () {
    config(['filament-lms.awards' => [
        'default' => 'Default',
        'safb' => 'Safe Arms For Babies',
    ]]);

    expect(app(AwardCertificateBlueprintFactory::class)->awardKeys('safb'))->toBe(['safb']);
});

it('extracts logos and copy from a custom award blade', function () {
    config(['filament-lms.awards' => [
        'decan' => 'Delaware Contraceptive Access Network',
    ]]);

    publishAwardView('decan', 'custom-award.blade.php');

    $blueprint = app(AwardCertificateBlueprintFactory::class)->make('decan');

    expect($blueprint->key)->toBe('decan')
        ->and($blueprint->label)->toBe('Delaware Contraceptive Access Network')
        ->and($blueprint->templateName)->toBe('Delaware Contraceptive Access Network Certificate')
        ->and($blueprint->logoPaths)->toBe(['/img/DE_DHSS-logo-red-wide.png'])
        ->and($blueprint->certifyingLine)->toBe('AWARDED TO:')
        ->and($blueprint->completedLine)->toBe('')
        ->and($blueprint->description)->toContain('Delaware Contraceptive Access Now')
        ->and($blueprint->includeCourseName)->toBeTrue();
});

it('hides the course name when the award blade does not print it', function () {
    config(['filament-lms.awards' => [
        'family-support-specialist' => 'Family Support Specialist Onboarding',
    ]]);

    publishAwardView('family-support-specialist', 'no-course-name.blade.php');

    $blueprint = app(AwardCertificateBlueprintFactory::class)->make('family-support-specialist');

    expect($blueprint->includeCourseName)->toBeFalse()
        ->and($blueprint->logoPaths)->toBe(['/img/DPH_logo_family.png'])
        ->and($blueprint->certifyingLine)->toBe('AWARDED TO:')
        ->and($blueprint->description)->toContain('Family Support Specialist');
});

it('uses the default award copy and configured logo', function () {
    config([
        'filament-lms.certificate_logo' => 'images/certificate-logo.png',
        'certificate-builder.token_sets.course.default_copy' => [
            'certifying_line' => 'This certifies that',
            'completed_line' => 'has successfully completed',
            'description' => '',
        ],
    ]);

    $blueprint = app(AwardCertificateBlueprintFactory::class)->make('default');

    expect($blueprint->templateName)->toBe('Default Certificate')
        ->and($blueprint->logoPaths)->toBe(['images/certificate-logo.png'])
        ->and($blueprint->certifyingLine)->toBe('This certificate is awarded to')
        ->and($blueprint->completedLine)->toBe('successfully completed')
        ->and($blueprint->includeCourseName)->toBeTrue();
});

it('resolves a logo that exists in the public directory', function () {
    $relative = 'img/award-logo.png';
    $absolute = public_path($relative);

    File::ensureDirectoryExists(dirname($absolute));
    File::put($absolute, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    expect(app(AwardCertificateBlueprintFactory::class)->resolveLogoAbsolutePath('/'.$relative))
        ->toBe($absolute)
        ->and(app(AwardCertificateBlueprintFactory::class)->resolveLogoAbsolutePath('missing/logo.png'))
        ->toBeNull();
});
