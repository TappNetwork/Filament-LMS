<?php

namespace Tapp\FilamentLms\Pages;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Test;

class CreateTestEntry extends Component
{
    public Test $test;

    public ?FilamentFormUser $entry = null;

    public bool $showResults = false;

    public float $percentageCorrect = 0;

    public int $questionsCorrect = 0;

    protected $listeners = ['entrySaved'];

    public function mount($test): void
    {
        if (! Auth::check()) {
            redirect()->route('login', [
                'redirect' => request()->fullUrl(),
            ]);

            return;
        }

        $this->test = $test->load('form');

        // Check if user already has an entry for this test
        $this->entry = FilamentFormUser::where('filament_form_id', $this->test->form->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($this->entry) {
            $this->populateCompletedTestData();
        }
    }

    public function render()
    {
        return view('filament-lms::filament.pages.create-test-entry');
    }

    public function entrySaved(FilamentFormUser $survey)
    {
        $this->entry = $survey;
        $this->populateCompletedTestData();
    }

    private function populateCompletedTestData(): void
    {
        $this->showResults = true;
        $this->percentageCorrect = $this->test->gradeEntry($this->entry);
        $this->questionsCorrect = $this->test->form->filamentFormFields->count() * ($this->percentageCorrect / 100);
    }
}
