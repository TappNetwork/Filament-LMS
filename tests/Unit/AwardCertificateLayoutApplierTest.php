<?php

declare(strict_types=1);

use Tapp\FilamentLms\Support\AwardCertificateBlueprint;
use Tapp\FilamentLms\Support\AwardCertificateLayoutApplier;
use Tapp\FilamentLms\Tests\Fakes\FakeCertificateLayout;

it('applies award copy and course-name visibility to a layout', function () {
    $blueprint = new AwardCertificateBlueprint(
        key: 'decan',
        label: 'Delaware Contraceptive Access Network',
        templateName: 'Delaware Contraceptive Access Network Certificate',
        logoPaths: ['/img/logo.png'],
        certifyingLine: 'AWARDED TO:',
        completedLine: '',
        description: 'Award-specific description.',
        includeCourseName: false,
    );

    $layout = app(AwardCertificateLayoutApplier::class)->apply(
        FakeCertificateLayout::default('course'),
        $blueprint,
    );

    $byId = collect($layout['elements'])->keyBy('id');

    expect($byId['certifying_line']['text'])->toBe('AWARDED TO:')
        ->and($byId['certifying_line']['visible'])->toBeTrue()
        ->and($byId['completed_line']['text'])->toBe('')
        ->and($byId['completed_line']['visible'])->toBeFalse()
        ->and($byId['description']['text'])->toBe('Award-specific description.')
        ->and($byId['description']['visible'])->toBeTrue()
        ->and($byId['course_name']['visible'])->toBeFalse();
});
