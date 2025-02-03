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

        try {
            return response()->download($mediaItem->getPath(), $mediaItem->file_name);
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
