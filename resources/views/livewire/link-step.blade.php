<div>
    <x-filament::section
        icon="heroicon-o-link"
        icon-color="primary"
        >
            <p class="mb-8">
                In order to complete this step, please review the following web page:
            </p>

            @if($this->getPreviewImage())
                <div class="mb-8 flex-1">
                    <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" wire:click="visit">
                        <img 
                            src="{{ $this->getPreviewImage() }}" 
                            class="step-material-container rounded-lg border border-gray-300 cursor-pointer"
                            alt="Preview of {{ $link->name }}"
                        >
                    </a>
                </div>
            @endif

            <x-filament::button
                wire:click="visit"
                href="{{$link->url}}"
                rel="noopener noreferrer"
                target="_blank"
                tag="a"
                >
                Visit
            </x-filament::button>
    </x-filament::section>

    <x-filament-lms::next-button :disabled="!$step->is_optional && !$visited" />
</div>
