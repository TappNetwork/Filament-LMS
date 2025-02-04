<div>
    <x-filament::section
        icon="heroicon-o-document"
        icon-color="primary"
        >
            <x-slot name="heading">
                File Download
            </x-slot>

            <p class="mb-8">
                In order to complete this step, please download and review the following document:
            </p>
            <x-filament::button wire:click="download">
            Download
        </x-filament::button>
    </x-filament::section>

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="gray" size="xl" wire:click="$dispatch('complete-step')" :disabled="! $downloaded">
            Next
        </x-filament::button>
    </div>
</div>
