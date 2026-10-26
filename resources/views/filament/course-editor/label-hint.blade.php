<span class="fi-lms-label-hint" style="display: inline-flex; align-items: center; gap: 0.375rem;">
    <span>{{ $label }}</span>
    <span
        x-on:mousedown.stop.prevent
        x-on:click.stop.prevent
        x-tooltip="{
            content: @js($tooltip),
            theme: $store.theme,
        }"
        class="text-primary-600 dark:text-primary-400"
        style="display: inline-flex; flex-shrink: 0;"
    >
        <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5" />
    </span>
</span>
