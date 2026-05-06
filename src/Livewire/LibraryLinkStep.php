<?php

namespace Tapp\FilamentLms\Livewire;

use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Tapp\FilamentLms\Models\Step;

class LibraryLinkStep extends Component
{
    /** @var \Tapp\FilamentLibrary\Models\LibraryItem&Model */
    public Model $libraryItem;

    public Step $step;

    public bool $visited;

    public function mount($step): void
    {
        $this->step = $step;
        $material = $step->material;
        if (! $material instanceof Model) {
            abort(404);
        }
        $this->libraryItem = $material;
        $this->visited = (bool) $step->completed_at;
    }

    public function render()
    {
        return view('filament-lms::livewire.library-link-step');
    }

    public function visit(): void
    {
        $this->visited = true;
    }

    public function getLinkUrl(): ?string
    {
        $url = $this->libraryItem->getAttribute('external_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Library links do not use LMS Link preview media; return null unless overridden by the host app.
     */
    public function getPreviewImage(): ?string
    {
        return null;
    }
}
