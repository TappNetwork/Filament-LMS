<div class="flex flex-col">
    <x-filament::section
        icon="heroicon-o-photo"
        icon-color="primary"
        class="flex-1 flex flex-col"
    >
        <p class="mb-8">
            Please review the following image:
        </p>

        <div class="mb-8 flex-1">
            @if($this->getImageUrl())
                <img 
                    src="{{ $this->getImageUrl() }}" 
                    class="step-material-container rounded-lg border border-gray-300"
                    alt="Step Image"
                />
            @endif
        </div>
    </x-filament::section>

    <x-filament-lms::next-button />
</div> 
