<?php

namespace Tapp\FilamentLms\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Tapp\FilamentLibrary\Models\LibraryItem;
use Tapp\FilamentLms\Models\Step;

class LibraryFileStep extends Component
{
    /** @var LibraryItem&Model */
    public Model $libraryItem;

    public Step $step;

    public bool $downloaded;

    public function mount($step): void
    {
        $this->step = $step;
        $material = $step->material;
        if (! $material instanceof Model) {
            abort(404);
        }
        $this->libraryItem = $material;
        $this->downloaded = (bool) $step->completed_at;
    }

    public function render()
    {
        return view('filament-lms::livewire.library-file-step');
    }

    public function download()
    {
        $this->downloaded = true;

        $media = $this->libraryItem->getFirstMedia();

        if (! $media) {
            return null;
        }

        return response()->download($media->getPath(), $media->file_name);
    }

    public function getPreviewUrl(): ?string
    {
        if (method_exists($this->libraryItem, 'getSecureUrl')) {
            $url = $this->libraryItem->getSecureUrl();

            return $url !== '' ? $url : null;
        }

        $media = $this->libraryItem->getFirstMedia();

        return $media?->getUrl();
    }
}
