<?php

namespace Tapp\FilamentLms\Livewire;

use Livewire\Component;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Step;

class ImageStep extends Component
{
    public Image $image;
    public Step $step;

    public function mount($step)
    {
        $this->step = $step;
        $this->image = $step->material;
    }

    public function render()
    {
        return view('filament-lms::livewire.image-step');
    }

    public function getImageUrl()
    {
        return $this->image->getFirstMediaUrl('image') ?: null;
    }
} 