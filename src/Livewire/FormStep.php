<?php

namespace Tapp\FilamentLms\Livewire;

use Livewire\Component;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;

class FormStep extends Component
{
    public FilamentForm $form;

    public Step $step;

    public int $seconds;

    public bool $formCompleted;

    protected $listeners = ['entrySaved'];

    public bool $showResults = false;

    public ?FilamentFormUser $entry = null;

    public function mount($step): void
    {
        $this->step = $step;
        $this->form = $step->material;
        $this->seconds = $step->seconds ?? 0;
        $this->entry = $step->formEntryForUser(auth()->id());
    }

    public function render()
    {
        return view('filament-lms::livewire.form-step');
    }

    public function entrySaved(int|FilamentFormUser $entry): void
    {
        if (is_int($entry)) {
            $entry = FilamentFormUser::query()->findOrFail($entry);
        }

        $this->entry = $entry;

        $this->step->complete();

        StepUser::query()
            ->where('user_id', auth()->id())
            ->where('step_id', $this->step->id)
            ->update(['filament_form_user_id' => $entry->id]);

        if ($this->step->lesson->course->isEvaluationCourse()) {
            $this->dispatch('complete-step');
        }
    }
}
