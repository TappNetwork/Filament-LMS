<?php

namespace Tapp\FilamentLms;

use Filament\Navigation\NavigationItem;

class LmsNavigation
{
    protected static array $extraNavigation = [];

    public static function addNavigation(string $panelId, NavigationItem|callable $item): void
    {
        static::$extraNavigation[$panelId][] = $item;
    }

    public static function getNavigation(string $panelId): array
    {
        return collect(static::$extraNavigation[$panelId] ?? [])
            ->map(fn ($item) => is_callable($item) ? $item() : $item)
            ->all();
    }
}
