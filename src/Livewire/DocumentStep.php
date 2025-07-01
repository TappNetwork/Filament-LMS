<?php

namespace Tapp\FilamentLms\Livewire;

use Livewire\Component;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Step;

class DocumentStep extends Component
{
    public Document $document;

    public Step $step;

    public bool $downloaded;

    public function mount($step)
    {
        $this->step = $step;
        $this->document = $step->material;
        $this->downloaded = (bool) $step->completed_at;
    }

    public function render()
    {
        return view('filament-lms::livewire.document-step');
    }

    public function download()
    {
        $this->downloaded = true;

        $mediaItem = $this->document->getFirstMedia();

        return response()->download($mediaItem->getPath(), $mediaItem->file_name);
    }

    public function getPdfUrl()
    {
        // Use the custom preview image if available, otherwise fallback to the original PDF URL
        $previewUrl = $this->document->getPreviewImageUrl();
        if ($previewUrl) {
            return $previewUrl;
        }
        $mediaItem = $this->document->getFirstMedia();

        return $mediaItem ? $mediaItem->getUrl() : null;
    }

    /**
     * Returns the preview image URL if it exists, otherwise null.
     */
    public function getPreviewImage()
    {
        return $this->document->getFirstMediaUrl('preview') ?: null;
    }
}
