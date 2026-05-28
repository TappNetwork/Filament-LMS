<?php

namespace Tapp\FilamentLms\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Services\ScormProgressService;

class DocumentStep extends Component
{
    public Document $document;

    public Step $step;

    public bool $downloaded;

    public function mount($step): void
    {
        $this->step = $step;
        $this->document = $step->material;
        $this->downloaded = (bool) $step->completed_at;

        $course = $step->lesson->course;
        $user = Auth::user();
        if ($course->isEmbeddedPlayer()
            && $course->completionMode() === CompletionMode::Html5
            && $user !== null
            && $course->launchStep()?->is($step)) {
            app(ScormProgressService::class)->recordStarted($course, $user);
        }
    }

    public function render()
    {
        return view('filament-lms::livewire.document-step');
    }

    public function download()
    {
        $this->downloaded = true;

        $mediaItem = $this->document->getFirstMedia();
        if ($mediaItem === null) {
            if ($this->document->hasScormPackage()) {
                return redirect()->to($this->getPdfUrl());
            }

            abort(404);
        }

        return response()->download($mediaItem->getPath(), $mediaItem->file_name);
    }

    public function getPdfUrl(): ?string
    {
        if ($this->document->hasScormPackage()) {
            return route('filament-lms.scorm-package.show', [
                'document' => $this->document->id,
                'entry' => $this->document->getScormLaunchPath(),
            ]);
        }

        $previewUrl = $this->document->getPreviewImageUrl();
        if ($previewUrl) {
            return $previewUrl;
        }

        return $this->document->getMediaUrl('default') ?: null;
    }

    /**
     * Returns the preview image URL if it exists, otherwise null.
     */
    public function getPreviewImage()
    {
        return $this->document->getMediaUrl('preview') ?: null;
    }
}
