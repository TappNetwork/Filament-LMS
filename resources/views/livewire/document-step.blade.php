<div>
    <x-filament::section
        class="flex-1 flex flex-col"
        >
            <div class="mb-8 flex-1">
                @if($this->getPreviewImage())
                    <img 
                        src="{{ $this->getPreviewImage() }}" 
                        alt="Document Preview Image" 
                        class="step-material-container rounded-lg border border-gray-300 cursor-pointer"
                        wire:click="download"
                    />
                @else
                    <iframe 
                        src="{{ $this->getPdfUrl() }}" 
                        class="step-material-container rounded-lg border border-gray-300"
                        title="PDF Preview"
                    ></iframe>
                @endif
            </div>

            <x-filament::button wire:click="download">
                Download
            </x-filament::button>
    </x-filament::section>

    <x-filament-lms::next-button />
</div>
