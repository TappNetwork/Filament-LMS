<div>
    <x-filament::section
        class="flex-1 flex flex-col"
    >
        <div class="mb-8 flex-1">
            @if($this->getImageUrl())
                <div
                    class="lms-image-preview"
                    x-data="{ open: false }"
                    x-effect="document.body.classList.toggle('lms-image-lightbox-open', open)"
                    @keydown.escape.window="open = false"
                >
                    <button
                        type="button"
                        class="lms-image-preview__trigger"
                        @click="open = true; $nextTick(() => $refs.closeBtn?.focus())"
                        aria-label="View larger image"
                    >
                        <div class="step-material-container">
                            <img
                                src="{{ $this->getImageUrl() }}"
                                class="rounded-lg border border-gray-300"
                                alt="Step Image"
                            />
                        </div>
                        <span class="lms-image-preview__hint">Tap to zoom</span>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        class="lms-image-lightbox"
                        role="dialog"
                        aria-modal="true"
                        aria-label="Image preview"
                        @click.self="open = false"
                    >
                        <button
                            type="button"
                            class="lms-image-lightbox__close"
                            x-ref="closeBtn"
                            @click="open = false"
                            aria-label="Close image preview"
                        >
                            Close
                        </button>
                        <div class="lms-image-lightbox__scroller">
                            <img
                                src="{{ $this->getImageUrl() }}"
                                alt="Step Image"
                            />
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament-lms::next-button />
</div>
