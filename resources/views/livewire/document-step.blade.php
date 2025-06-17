<div class="flex flex-col min-h-[80vh]">
    <x-filament::section
        icon="heroicon-o-document"
        icon-color="primary"
        class="flex-1 flex flex-col"
        >
            <x-slot name="heading">
                File Download
            </x-slot>

            <p class="mb-8">
                In order to complete this step, please download and review the following document:
            </p>

            <div class="mb-8 flex-1">
                <iframe 
                    src="{{ $this->getPdfUrl() }}" 
                    class="w-full h-[75vh] min-h-[500px] rounded-lg border border-gray-300"
                    title="PDF Preview"
                ></iframe>
            </div>

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
