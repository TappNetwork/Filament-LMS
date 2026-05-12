<div>
    <x-filament::section class="flex-1 flex flex-col">
        <div class="mb-8 flex-1">
            @if($url = $this->getPreviewUrl())
                <iframe
                    src="{{ $url }}"
                    class="step-material-container rounded-lg border border-gray-300"
                    title="{{ $libraryItem->name }}"
                ></iframe>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    No preview is available for this file. Use Download to open it.
                </p>
            @endif
        </div>

        <x-filament::button wire:click="download" type="button">
            Download
        </x-filament::button>
    </x-filament::section>

    <x-filament-lms::next-button />
</div>
