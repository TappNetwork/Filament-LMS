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
        includeSignatures: false,
        border: [
            'style' => 'gradient',
            'width' => 6,
            'color' => '#a3e635',
            'gradient' => 'linear-gradient(to right, #a3e635, #0ea5e9, #67e8f9)',
        ],
        header: [
            'enabled' => true,
            'height' => 220,
            'title_bind' => 'course_name',
            'subtitle' => 'CERTIFICATE OF COMPLETION',
        ],
        headerImagePath: '/img/header-green.jpg',
    );

    $layout = FakeCertificateLayout::default('course');
    $layout['elements'][] = [
        'id' => 'logo_1',
        'type' => 'image',
        'y' => 30,
        'visible' => true,
    ];
    $layout['elements'][] = [
        'id' => 'signature_1',
        'type' => 'signature',
        'visible' => true,
    ];
    $layout['elements'][] = [
        'id' => 'signature_2',
        'type' => 'signature',
        'visible' => true,
    ];

    $layout = app(AwardCertificateLayoutApplier::class)->apply($layout, $blueprint);

    $byId = collect($layout['elements'])->keyBy('id');

    expect($byId['certifying_line']['text'])->toBe('AWARDED TO:')
        ->and($byId['certifying_line']['visible'])->toBeTrue()
        ->and($byId['completed_line']['text'])->toBe('')
        ->and($byId['completed_line']['visible'])->toBeFalse()
        ->and($byId['description']['text'])->toBe('Award-specific description.')
        ->and($byId['description']['visible'])->toBeTrue()
        ->and($byId['course_name']['visible'])->toBeFalse()
        ->and($byId['recipient_name_display']['visible'])->toBeFalse()
        ->and($byId['recipient_name']['visible'])->toBeTrue()
        ->and($byId['logo_1']['y'])->toBe(560)
        ->and($layout['signature_count'])->toBe(0)
        ->and($byId->has('signature_1'))->toBeFalse()
        ->and($byId->has('signature_2'))->toBeFalse()
        ->and($layout['border']['style'])->toBe('gradient')
        ->and($layout['header']['enabled'])->toBeTrue()
        ->and($layout['header']['title_bind'])->toBe('course_name');
});

it('keeps awarded-to text from overlapping the recipient name when a header is applied', function () {
    $blueprint = new AwardCertificateBlueprint(
        key: 'decan',
        label: 'Delaware Contraceptive Access Network',
        templateName: 'Delaware Contraceptive Access Network Certificate',
        logoPaths: [],
        certifyingLine: 'AWARDED TO:',
        completedLine: '',
        description: 'Award-specific description.',
        includeCourseName: true,
        includeSignatures: false,
        border: [],
        header: [
            'enabled' => true,
            'height' => 220,
            'title_bind' => 'course_name',
        ],
        headerImagePath: '/img/header-green.jpg',
    );

    $layout = [
        'elements' => [
            [
                'id' => 'certifying_line',
                'type' => 'text',
                'text' => 'This certifies that',
                'y' => 200,
                'h' => 36,
                'visible' => true,
            ],
            [
                'id' => 'recipient_name',
                'type' => 'text',
                'bind' => 'recipient_name',
                'y' => 245,
                'h' => 45,
                'visible' => true,
            ],
            [
                'id' => 'description',
                'type' => 'text',
                'text' => '',
                'y' => 390,
                'h' => 80,
                'visible' => true,
            ],
        ],
    ];

    $layout = app(AwardCertificateLayoutApplier::class)->apply($layout, $blueprint);
    $byId = collect($layout['elements'])->keyBy('id');

    expect($byId['recipient_name']['y'])
        ->toBeGreaterThanOrEqual($byId['certifying_line']['y'] + $byId['certifying_line']['h'] + 28)
        ->and($byId['description']['y'])->toBeGreaterThan($byId['recipient_name']['y'] + $byId['recipient_name']['h']);
});

it('keeps signature fields when the award includes them', function () {
    $blueprint = new AwardCertificateBlueprint(
        key: 'default',
        label: 'Default',
        templateName: 'Default Certificate',
        logoPaths: [],
        certifyingLine: 'This certifies that',
        completedLine: 'has successfully completed',
        description: '',
        includeCourseName: true,
        includeSignatures: true,
        border: [],
        header: [],
        headerImagePath: null,
    );

    $layout = FakeCertificateLayout::default('course');
    $layout['elements'][] = [
        'id' => 'signature_1',
        'type' => 'signature',
        'visible' => true,
    ];

    $layout = app(AwardCertificateLayoutApplier::class)->apply($layout, $blueprint);

    expect($layout['signature_count'])->toBe(2)
        ->and(collect($layout['elements'])->pluck('id'))->toContain('signature_1');
});
