<?php

namespace Tapp\FilamentLms\Livewire;

use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Models\Step;
use Livewire\Component;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Livewire\Attributes\On;

class VideoStep extends Component
{
    public Video $video;
    public Step $step;
    public int $seconds;
    public bool $videoCompleted;

    public function mount($step)
    {
        $this->step = $step;
        $this->video = $step->material;
        $this->seconds = $step->seconds ?? 0;
        $this->videoCompleted = (bool) $step->completed_at;
    }

    #[On('video-progress')]
    public function videoProgress(int $seconds)
    {
        $this->step->videoProgress($seconds);
    }

    #[On('video-ended')]
    public function videoEnded()
    {
        $this->videoCompleted = true;

        $this->step->complete();
    }

    public function render()
    {
        return view('filament-lms::livewire.video-step');
    }
}
