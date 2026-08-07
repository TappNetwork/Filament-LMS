<?php

declare(strict_types=1);

use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Tapp\FilamentLms\Enums\CourseContext;
use Tapp\FilamentLms\Enums\NavigationMode;
use Tapp\FilamentLms\Support\LmsPrimaryNavigation;

beforeEach(function (): void {
    LmsPrimaryNavigation::flushMirrorCache();
});

it('defaults navigation mode to native', function (): void {
    config(['filament-lms.navigation.mode' => null]);

    expect(NavigationMode::fromConfig())->toBe(NavigationMode::Native);
});

it('resolves mirror_panel navigation mode from config', function (): void {
    config(['filament-lms.navigation.mode' => 'mirror_panel']);

    expect(NavigationMode::fromConfig())->toBe(NavigationMode::MirrorPanel)
        ->and(NavigationMode::fromConfig()->isMirrorPanel())->toBeTrue();
});

it('defaults course context to replace_sidebar', function (): void {
    config(['filament-lms.navigation.course_context' => null]);

    expect(CourseContext::fromConfig())->toBe(CourseContext::ReplaceSidebar)
        ->and(CourseContext::fromConfig()->replacesSidebar())->toBeTrue();
});

it('resolves sub_navigation course context from config', function (): void {
    config(['filament-lms.navigation.course_context' => 'sub_navigation']);

    expect(CourseContext::fromConfig())->toBe(CourseContext::SubNavigation)
        ->and(CourseContext::fromConfig()->usesSubNavigation())->toBeTrue();
});

it('builds native primary navigation with Courses item', function (): void {
    config([
        'filament-lms.navigation.mode' => 'native',
        'filament-lms.navigation.course_context' => 'replace_sidebar',
    ]);

    $builder = LmsPrimaryNavigation::apply(new NavigationBuilder);
    $groups = $builder->getNavigation();

    $labels = collect($groups)
        ->flatMap(fn ($group) => collect($group->getItems())->map(fn (NavigationItem $item) => $item->getLabel()))
        ->all();

    expect($labels)->toContain('Courses');
});

it('hides course topbar items when using sub_navigation', function (): void {
    config([
        'filament-lms.navigation.mode' => 'native',
        'filament-lms.navigation.course_context' => 'sub_navigation',
    ]);

    expect(LmsPrimaryNavigation::topbarItems())->toBe([]);
});

it('returns Courses topbar items in native replace_sidebar mode', function (): void {
    config([
        'filament-lms.navigation.mode' => 'native',
        'filament-lms.navigation.course_context' => 'replace_sidebar',
    ]);

    $labels = collect(LmsPrimaryNavigation::topbarItems())
        ->map(fn (NavigationItem $item) => $item->getLabel())
        ->all();

    expect($labels)->toContain('Courses');
});
