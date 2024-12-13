<?php

namespace Tapp\FilamentLms\Livewire;

use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentLms\Models\Step;
use Livewire\Component;
use Livewire\Attributes\On;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class FormStep extends Component
{
    public FilamentForm $form;
    public Step $step;
    public int $seconds;
    public bool $formCompleted;
    protected $listeners = ['entrySaved'];
    public bool $showResults = false;
    public ?FilamentFormUser $entry = null;

    public function mount($step)
    {
        $this->step = $step;
        $this->form = $step->material;
        $this->seconds = $step->seconds ?? 0;
        $this->entry = FilamentFormUser::where('filament_form_id', $this->form->id)
            ->where('user_id', auth()->user()->id)
            ->first();
    }

    public function render()
    {
        return view('filament-lms::livewire.form-step');
    }

    public function entrySaved(FilamentFormUser $entry)
    {
        $this->entry = $entry;

        $this->step->complete();
    }
}
