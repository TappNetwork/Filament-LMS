@props([
    'navigation',
])

<div
    {{
        $attributes->class([
            'fi-topbar sticky top-0 z-20 overflow-x-clip fi-topbar-with-navigation',
        ])
    }}
>
    <nav
        class="flex h-16 items-center gap-x-4 bg-white px-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        @if (filament()->hasNavigation())
            <ul class="me-4 hidden items-center gap-x-4 lg:flex">
                @foreach ($navigation as $group)
                    @if ($groupLabel = $group->getLabel())
                        <x-filament::dropdown
                            placement="bottom-start"
                            teleport
                            :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraTopbarAttributeBag())"
                        >
                            <x-slot name="trigger">
                                <x-filament-panels::topbar.item
                                    :active="$group->isActive()"
                                    :icon="$group->getIcon()"
                                >
                                    {{ $groupLabel }}
                                </x-filament-panels::topbar.item>
                            </x-slot>

                            @php
                                $lists = [];

                                foreach ($group->getItems() as $item) {
                                    if ($childItems = $item->getChildItems()) {
                                        $lists[] = [
                                            $item,
                                            ...$childItems,
                                        ];
                                        $lists[] = [];

                                        continue;
                                    }

                                    if (empty($lists)) {
                                        $lists[] = [$item];

                                        continue;
                                    }

                                    $lists[count($lists) - 1][] = $item;
                                }

                                if (empty($lists[count($lists) - 1])) {
                                    array_pop($lists);
                                }
                            @endphp

                            @foreach ($lists as $list)
                                <x-filament::dropdown.list>
                                    @foreach ($list as $item)
                                        @php
                                            $itemIsActive = $item->isActive();
                                        @endphp

                                        <x-filament::dropdown.list.item
                                            :badge="$item->getBadge()"
                                            :badge-color="$item->getBadgeColor()"
                                            :badge-tooltip="$item->getBadgeTooltip()"
                                            :color="$itemIsActive ? 'primary' : 'gray'"
                                            :href="$item->getUrl()"
                                            :icon="$itemIsActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()"
                                            tag="a"
                                            :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
                                        >
                                            {{ $item->getLabel() }}
                                        </x-filament::dropdown.list.item>
                                    @endforeach
                                </x-filament::dropdown.list>
                            @endforeach
                        </x-filament::dropdown>
                    @else
                        @foreach ($group->getItems() as $item)
                            <x-filament-panels::topbar.item
                                :active="$item->isActive()"
                                :active-icon="$item->getActiveIcon()"
                                :badge="$item->getBadge()"
                                :badge-color="$item->getBadgeColor()"
                                :badge-tooltip="$item->getBadgeTooltip()"
                                :icon="$item->getIcon()"
                                :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                                :url="$item->getUrl()"
                            >
                                {{ $item->getLabel() }}
                            </x-filament-panels::topbar.item>
                        @endforeach
                    @endif
                @endforeach
            </ul>
        @endif
    </nav>
</div>
