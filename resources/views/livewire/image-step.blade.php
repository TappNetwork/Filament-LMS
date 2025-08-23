<div>
    <x-filament::section
        class="flex-1 flex flex-col"
    >
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

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="gray" wire:click="$dispatch('complete-step')" class="next-button">
            Next
        </x-filament::button>
    </div>
</div> 
