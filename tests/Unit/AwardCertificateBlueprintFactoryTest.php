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
        ->and($blueprint->includeCourseName)->toBeTrue()
        ->and($blueprint->includeSignatures)->toBeFalse()
        ->and($blueprint->border['style'])->toBe('gradient')
        ->and($blueprint->border['gradient'])->toBe('linear-gradient(to right, #a3e635, #0ea5e9, #67e8f9)')
        ->and($blueprint->header['enabled'])->toBeTrue()
        ->and($blueprint->header['title_bind'])->toBe('course_name')
        ->and($blueprint->header['subtitle'])->toBe('CERTIFICATE OF COMPLETION')
        ->and($blueprint->headerImagePath)->toBe('/img/header-green.jpg');
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
        ->and($blueprint->description)->toContain('Family Support Specialist')
        ->and($blueprint->includeSignatures)->toBeFalse();
});

it('keeps signatures when the award blade has signature fields', function () {
    config(['filament-lms.awards' => [
        'signed' => 'Signed Award',
    ]]);

    publishAwardView('signed', 'signed-award.blade.php');

    $blueprint = app(AwardCertificateBlueprintFactory::class)->make('signed');

    expect($blueprint->includeSignatures)->toBeTrue();
});

it('follows the LMS signature config for the default award blade', function () {
    config(['filament-lms.certificate_show_signatures' => false]);

    expect(app(AwardCertificateBlueprintFactory::class)->make('default')->includeSignatures)->toBeFalse();

    config(['filament-lms.certificate_show_signatures' => true]);

    expect(app(AwardCertificateBlueprintFactory::class)->make('default')->includeSignatures)->toBeTrue();
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
        ->and($blueprint->includeCourseName)->toBeTrue()
        ->and($blueprint->border['style'])->toBe('double')
        ->and($blueprint->border['color'])->toBe('#a1a1aa')
        ->and($blueprint->header['enabled'])->toBeFalse()
        ->and($blueprint->headerImagePath)->toBeNull();
});

it('extracts a solid frame color from an award blade', function () {
    config(['filament-lms.awards' => [
        'family-support-specialist' => 'Family Support Specialist Onboarding',
    ]]);

    publishAwardView('family-support-specialist', 'solid-border-award.blade.php');

    $blueprint = app(AwardCertificateBlueprintFactory::class)->make('family-support-specialist');

    expect($blueprint->border['style'])->toBe('solid')
        ->and($blueprint->border['color'])->toBe('#B5498F')
        ->and($blueprint->border['width'])->toBe(6)
        ->and($blueprint->header['enabled'])->toBeFalse();
});

it('extracts a banner header from an award blade', function () {
    config(['filament-lms.awards' => [
        'family-support-specialist' => 'Family Support Specialist Onboarding',
    ]]);

    publishAwardView('family-support-specialist', 'header-banner-award.blade.php');

    $blueprint = app(AwardCertificateBlueprintFactory::class)->make('family-support-specialist');

    expect($blueprint->header['enabled'])->toBeTrue()
        ->and($blueprint->header['height'])->toBe(300)
        ->and($blueprint->header['background_color'])->toBe('#B5498F')
        ->and($blueprint->header['background_size'])->toBe('60% auto')
        ->and($blueprint->header['title_bind'])->toBe('')
        ->and($blueprint->headerImagePath)->toBe('/img/home-visiting-header-certificate.png');
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
