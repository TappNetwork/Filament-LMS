<?php

namespace Tapp\FilamentLms\Livewire;

use Livewire\Component;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Step;

class LinkStep extends Component
{
    public Link $link;

    public Step $step;

    public bool $visited;

    public function mount($step)
    {
        $this->step = $step;
        $this->link = $step->material;
        $this->visited = (bool) $step->completed_at;
    }

    public function render()
    {
        return view('filament-lms::livewire.link-step');
    }

    public function visit()
    {
        $this->visited = true;
    }
}
