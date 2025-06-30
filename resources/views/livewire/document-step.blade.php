<div class="flex flex-col">
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
                @if($this->getPreviewImage())
                    <img 
                        src="{{ $this->getPreviewImage() }}" 
                        alt="Document Preview Image" 
                        class="rounded-lg border border-gray-300"
                    />
                @else
                    <iframe 
                        src="{{ $this->getPdfUrl() }}" 
                        class="w-full rounded-lg border border-gray-300"
                        style="min-height: 48vh;"
                        title="PDF Preview"
                    ></iframe>
                @endif
            </div>

            <x-filament::button wire:click="download">
                Download
            </x-filament::button>
    </x-filament::section>

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <!-- TODO: could disable button if not downloaded. but the preview has an alternative download button we cannot track -->
        <x-filament::button color="gray" size="xl" wire:click="$dispatch('complete-step')">
            Next
        </x-filament::button>
    </div>
</div>
