<?php

declare(strict_types=1);

it('hides the Filament sidebar on embedded player pages only when collapsible desktop is off', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/dist/filament-lms.css');

    expect($css)->toBeString()
        ->and($css)->toContain('body.lms-embedded-player:not(.fi-body-has-sidebar-collapsible-on-desktop) .fi-sidebar')
        ->and($css)->toContain('body.lms-embedded-player:not(.fi-body-has-sidebar-collapsible-on-desktop) .fi-main-ctn')
        ->and($css)->toContain('body.lms-embedded-player .fi-main')
        ->and($css)->not->toContain("body.lms-embedded-player .fi-sidebar {\n    display: none !important;\n}");
});
