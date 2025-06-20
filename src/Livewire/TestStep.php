<?php

namespace Tapp\FilamentLms\Livewire;

use Livewire\Component;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class TestStep extends Component
{
    public Test $test;

    public Step $step;

    public bool $testCompleted;

    public ?FilamentFormUser $entry = null;

    protected $listeners = ['entrySaved'];

    public function mount($step)
    {
        $this->step = $step;
        $this->test = $step->material;
        $this->testCompleted = (bool) $step->completed_at;
        
        // Check if user has already taken this test
        $this->entry = FilamentFormUser::where('filament_form_id', $this->test->form->id)
            ->where('user_id', auth()->user()->id)
            ->first();
    }

    public function render()
    {
        return view('filament-lms::livewire.test-step');
    }

    public function entrySaved(FilamentFormUser $entry)
    {
        $this->entry = $entry;
        $this->testCompleted = true;
        $this->step->complete();
    }
} 