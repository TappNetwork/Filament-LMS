<?php

namespace Tapp\FilamentLms\Livewire;

use Livewire\Component;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;

class VimeoVideo extends Component
{
    public Video $video;

    public Step $step;

    public function mount($step)
    {
        $this->step = $step;
        $this->video = $step->material;
    }

    public function videoProgress(int $seconds)
    {
        $this->dispatch('video-progress', $seconds);
    }

    public function videoEnded()
    {
        $this->dispatch('video-ended');
    }

    public function render()
    {
        return view('filament-lms::livewire.vimeo-video');
    }
}
