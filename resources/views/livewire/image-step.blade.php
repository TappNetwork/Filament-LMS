<div>
    <x-filament::section
        class="flex-1 flex flex-col"
    >
        <div class="mb-8 flex-1">
            @if($this->getImageUrl())
                <div class="step-material-container">
                    <img
                        src="{{ $this->getImageUrl() }}"
                        class="rounded-lg border border-gray-300"
                        alt="Step Image"
                    />
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament-lms::next-button />
</div> 
