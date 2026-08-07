<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Illuminate\Support\Collection;
use Tapp\FilamentLms\Enums\CourseContext;
use Tapp\FilamentLms\Enums\NavigationMode;
use Tapp\FilamentLms\LmsNavigation;
use Tapp\FilamentLms\Pages\Dashboard;

final class LmsPrimaryNavigation
{
    /**
     * @var array<NavigationGroup>|null
     */
    private static ?array $mirroredGroupsCache = null;

    public static function apply(NavigationBuilder $builder): NavigationBuilder
    {
        if (Filament::getCurrentOrDefaultPanel()->getId() !== 'lms') {
            return $builder;
        }

        $course = CourseStepNavigation::currentCourse();
        $courseContext = CourseContext::fromConfig();

        if ($course !== null && $courseContext->replacesSidebar()) {
            return $builder->groups(CourseStepNavigation::groupsForCourse($course));
        }

        return match (NavigationMode::fromConfig()) {
            NavigationMode::MirrorPanel => self::applyMirrored($builder),
            NavigationMode::Native => self::applyNative($builder),
        };
    }

    /**
     * @return array<NavigationItem>
     */
    public static function topbarItems(): array
    {
        if (CourseContext::fromConfig()->usesSubNavigation()) {
            return [];
        }

        if (NavigationMode::fromConfig()->isMirrorPanel()) {
            return [];
        }

        return [
            ...LmsNavigation::getNavigation('lms'),
            self::nativeCoursesItem(),
        ];
    }

    private static function applyNative(NavigationBuilder $builder): NavigationBuilder
    {
        return $builder->items([
            ...LmsNavigation::getNavigation('lms'),
            self::nativeCoursesItem(),
        ]);
    }

    private static function applyMirrored(NavigationBuilder $builder): NavigationBuilder
    {
        $groups = self::mirroredGroups();
        $hooked = LmsNavigation::getNavigation('lms');

        if ($hooked !== []) {
            $builder->items($hooked);
        }

        foreach ($groups as $group) {
            if (blank($group->getLabel())) {
                $builder->items(self::normalizeLmsItems($group->getItems()));

                continue;
            }

            $builder->group(
                NavigationGroup::make($group->getLabel())
                    ->icon($group->getIcon())
                    ->collapsible($group->isCollapsible())
                    ->items(self::normalizeLmsItems($group->getItems())),
            );
        }

        return $builder;
    }

    /**
     * @return array<NavigationGroup>
     */
    private static function mirroredGroups(): array
    {
        if (self::$mirroredGroupsCache !== null) {
            return self::$mirroredGroupsCache;
        }

        $panelId = config('filament-lms.navigation.mirror_panel_id');

        if (! is_string($panelId) || $panelId === '') {
            self::$mirroredGroupsCache = [];

            return self::$mirroredGroupsCache;
        }

        try {
            $sourcePanel = Filament::getPanel($panelId);
        } catch (\Throwable) {
            self::$mirroredGroupsCache = [];

            return self::$mirroredGroupsCache;
        }

        self::$mirroredGroupsCache = self::navigationGroupsFromPanel($sourcePanel);

        return self::$mirroredGroupsCache;
    }

    /**
     * Resolve source-panel navigation while that panel is current, then freeze
     * item URLs as strings so later rendering on the LMS panel does not call
     * Resource::getUrl() against missing LMS routes.
     *
     * Filament binds {@see NavigationManager} as a singleton keyed off the current
     * panel at construction time. Building the source panel's nav (or calling
     * hasNavigation() which resolves the LMS builder first) would otherwise leave
     * an app-panel manager in the container; the next LMS getNavigation() would
     * then evaluate Library URL closures against filament.lms.* routes.
     *
     * @return array<NavigationGroup>
     */
    private static function navigationGroupsFromPanel(Panel $sourcePanel): array
    {
        $previousPanel = Filament::getCurrentPanel();

        Filament::setCurrentPanel($sourcePanel);
        app()->forgetInstance(NavigationManager::class);

        try {
            return collect($sourcePanel->getNavigation())
                ->map(fn (NavigationGroup $group): NavigationGroup => self::freezeGroup($group))
                ->all();
        } finally {
            app()->forgetInstance(NavigationManager::class);

            if ($previousPanel !== null) {
                Filament::setCurrentPanel($previousPanel);
            }
        }
    }

    private static function freezeGroup(NavigationGroup $group): NavigationGroup
    {
        $items = Collection::wrap($group->getItems())
            ->map(fn (NavigationItem $item): NavigationItem => self::freezeItem($item))
            ->all();

        if (blank($group->getLabel())) {
            return NavigationGroup::make()->items($items);
        }

        return NavigationGroup::make($group->getLabel())
            ->icon($group->getIcon())
            ->collapsible($group->isCollapsible())
            ->items($items);
    }

    private static function freezeItem(NavigationItem $item): NavigationItem
    {
        $frozen = NavigationItem::make($item->getLabel())
            ->icon($item->getIcon())
            ->sort($item->getSort())
            ->badge($item->getBadge(), $item->getBadgeColor())
            ->visible($item->isVisible())
            ->url($item->getUrl(), $item->shouldOpenUrlInNewTab())
            ->isActiveWhen(fn (): bool => false);

        $childItems = Collection::wrap($item->getChildItems());

        if ($childItems->isNotEmpty()) {
            $frozen->childItems(
                $childItems
                    ->map(fn (NavigationItem $child): NavigationItem => self::freezeItem($child))
                    ->all(),
            );
        }

        return $frozen;
    }

    /**
     * @param  array<NavigationItem>|Collection<int, NavigationItem>  $items
     * @return array<NavigationItem>
     */
    private static function normalizeLmsItems(array|Collection $items): array
    {
        $label = self::lmsItemLabel();

        return Collection::wrap($items)
            ->map(function (NavigationItem $item) use ($label): NavigationItem {
                if (! self::itemLooksLikeLmsEntry($item)) {
                    return $item;
                }

                return NavigationItem::make($label)
                    ->icon($item->getIcon() ?? 'heroicon-o-academic-cap')
                    ->sort($item->getSort())
                    ->visible($item->isVisible())
                    ->isActiveWhen(fn (): bool => Filament::getCurrentOrDefaultPanel()->getId() === 'lms')
                    ->url(fn (): string => Dashboard::getUrl());
            })
            ->all();
    }

    private static function itemLooksLikeLmsEntry(NavigationItem $item): bool
    {
        $label = (string) $item->getLabel();
        $configuredLabel = self::lmsItemLabel();

        if (strcasecmp($label, $configuredLabel) === 0 || strcasecmp($label, 'Courses') === 0 || strcasecmp($label, 'LMS') === 0) {
            return true;
        }

        $url = $item->getUrl();

        return is_string($url) && str_contains($url, '/lms');
    }

    private static function nativeCoursesItem(): NavigationItem
    {
        return NavigationItem::make('Courses')
            ->icon('heroicon-o-academic-cap')
            ->isActiveWhen(fn (): bool => request()->routeIs(Dashboard::getRouteName()))
            ->url(fn (): string => Dashboard::getUrl());
    }

    private static function lmsItemLabel(): string
    {
        $label = config('filament-lms.navigation.lms_item_label', 'LMS');

        return is_string($label) && $label !== '' ? $label : 'LMS';
    }

    public static function flushMirrorCache(): void
    {
        self::$mirroredGroupsCache = null;
    }
}
