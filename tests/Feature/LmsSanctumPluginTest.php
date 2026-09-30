<?php

declare(strict_types=1);

use Devtical\Sanctum\SanctumPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Tapp\FilamentLms\Lms;

beforeEach(function () {
    if (! class_exists(SanctumPlugin::class)) {
        $this->markTestSkipped('devtical/filament-sanctum is required for Sanctum UI tests.');
    }
});

test('admin panel with Lms plugin registers filament-sanctum', function () {
    config(['filament-lms.sanctum_ui.enabled' => true]);

    $panel = Filament::getPanel('admin');

    expect($panel->hasPlugin('filament-sanctum'))->toBeTrue();
});

test('learner panel does not register filament-sanctum', function () {
    $panel = Filament::getPanel('lms');

    expect($panel->hasPlugin('filament-sanctum'))->toBeFalse();
});

test('sanctum_ui disabled skips filament-sanctum registration', function () {
    config(['filament-lms.sanctum_ui.enabled' => false]);

    $panel = Panel::make()->id('admin')->path('admin-off');

    (new Lms)->register($panel);

    expect($panel->hasPlugin('filament-sanctum'))->toBeFalse();
});

test('existing filament-sanctum plugin is not registered again', function () {
    config(['filament-lms.sanctum_ui.enabled' => true]);

    $existing = SanctumPlugin::make();
    $panel = Panel::make()->id('admin')->path('admin-existing')->plugin($existing);

    (new Lms)->register($panel);

    expect($panel->hasPlugin('filament-sanctum'))->toBeTrue()
        ->and($panel->getPlugin('filament-sanctum'))->toBe($existing);
});
