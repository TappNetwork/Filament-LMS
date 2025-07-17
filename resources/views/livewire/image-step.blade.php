<div class="flex flex-col">
    <x-filament::section
        icon="heroicon-o-photo"
        icon-color="primary"
        class="flex-1 flex flex-col"
    >
        <x-slot name="heading">
            Image Step
        </x-slot>

        <p class="mb-8">
            Please review the following image:
        </p>

        <div class="mb-8 flex-1">
            @if($this->getImageUrl())
                <img 
                    src="{{ $this->getImageUrl() }}" 
                    class="rounded-lg border border-gray-300"
                    alt="Step Image"
                />
            @endif
        </div>
    </x-filament::section>

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="primary" size="xl" wire:click="$dispatch('complete-step')">
            Next
        </x-filament::button>
    </div>
</div> 