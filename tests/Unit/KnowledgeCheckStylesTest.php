<?php

declare(strict_types=1);

it('stops knowledge check answer cards from overlapping the question text', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/dist/filament-lms.css');

    // Filament gives stacked radio lists margin-top: -1rem; the card layout must cancel it.
    preg_match(
        '/\.lms-test-form-wrapper \.lms-knowledge-check-radio-field \.fi-fo-radio \{(?<body>[^}]*)\}/',
        $css,
        $matches,
    );

    expect($matches)->toHaveKey('body')
        ->and($matches['body'])->toMatch('/(^|;|\s)margin-top:\s*0;/');
});

it('ships the same knowledge check styles in dist and resources', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/dist/filament-lms.css'))
        ->toBe(file_get_contents($root.'/resources/css/plugin.css'));
});
