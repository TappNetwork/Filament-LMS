<div>
    <x-filament::section
        icon="heroicon-o-link"
        icon-color="primary"
    >
        <p class="mb-8">
            In order to complete this step, please review the following resource from the library:
        </p>

        @if($description = $libraryItem->getAttribute('link_description'))
            <p class="mb-6 text-sm text-gray-700 dark:text-gray-300">
                {{ $description }}
            </p>
        @endif

        @if($linkUrl = $this->getLinkUrl())
            @if($this->getPreviewImage())
                <div class="mb-8 flex-1">
                    <a
                        href="{{ $linkUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        wire:click="visit"
                    >
                        <img
                            src="{{ $this->getPreviewImage() }}"
                            class="step-material-container rounded-lg border border-gray-300 cursor-pointer"
                            alt="Preview of {{ $libraryItem->name }}"
                        />
                    </a>
                </div>
            @endif

            <x-filament::button
                wire:click="visit"
                href="{{ $linkUrl }}"
                rel="noopener noreferrer"
                target="_blank"
                tag="a"
            >
                Open link
            </x-filament::button>
        @else
            <p class="text-sm text-danger-600 dark:text-danger-400">
                This library link is missing a URL.
            </p>
        @endif
    </x-filament::section>

    <x-filament-lms::next-button :disabled="!$step->is_optional && !$visited" />
</div>
