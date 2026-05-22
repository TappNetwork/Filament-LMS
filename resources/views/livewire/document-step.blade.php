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
                @elseif ($document->hasScormPackage())
                    <div class="step-material-container step-material-container--interactive w-full">
                        <iframe
                            src="{{ $this->getPdfUrl() }}"
                            class="h-full w-full rounded-lg border border-gray-300"
                            title="Course content"
                        ></iframe>
                    </div>
                @else
                    <iframe
                        src="{{ $this->getPdfUrl() }}"
                        class="step-material-container rounded-lg border border-gray-300"
                        title="PDF Preview"
                    ></iframe>
                @endif
            </div>

            @unless ($step->lesson->course->isEmbeddedPlayer())
                <x-filament::button wire:click="download">
                    Download
                </x-filament::button>
            @endunless
    </x-filament::section>

    @unless ($step->lesson->course->isEmbeddedPlayer())
        <x-filament-lms::next-button />
    @endunless
</div>
