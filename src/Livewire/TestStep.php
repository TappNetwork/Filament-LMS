<?php

namespace Tapp\FilamentLms\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;

class TestStep extends Component
{
    public Test $test;

    public Step $step;

    public bool $testCompleted;

    public bool $testPassed = false;

    public ?FilamentFormUser $entry = null;

    protected $listeners = ['entrySaved'];

    public function mount($step)
    {
        $this->step = $step;
        $this->step->load(['retryStep.lesson.course']);
        $this->test = $step->material;
        $this->testCompleted = (bool) $step->completed_at;

        // Check if user has already taken this test
        $user = Auth::user();
        $this->entry = FilamentFormUser::where('filament_form_id', $this->test->form->id)
            ->where('user_id', $user->id)
            ->first();

        // Always check test results if entry exists
        if ($this->entry) {
            $this->checkTestResults();
            // If step was completed but test wasn't passed (and perfect score is required), uncomplete it
            if ($this->testCompleted && $this->step->require_perfect_score && ! $this->testPassed) {
                $this->testCompleted = false;
                // Delete the progress record to properly uncomplete the step in the database
                $this->step->progress()->delete();
            }
        }
    }

    public function render()
    {
        return view('filament-lms::livewire.test-step');
    }

    public function entrySaved(FilamentFormUser $entry)
    {
        // Verify the entry belongs to the current user
        if ($entry->getAttribute('user_id') !== Auth::id()) {
            return;
        }

        $this->entry = $entry;
        $this->checkTestResults();

        // Only complete the step if test passed (or if perfect score is not required)
        if ($this->testPassed || ! $this->step->require_perfect_score) {
            $this->testCompleted = true;
            $this->step->complete();
        } else {
            $this->testCompleted = false;
        }
    }

    public function retakeTest(): void
    {
        if (! $this->entry) {
            return;
        }

        // Delete the old submission
        $this->entry->delete();

        // Reset entry and completion status
        $this->entry = null;
        $this->testPassed = false;
        $this->testCompleted = false;

        // Reset step completion if it was marked as completed
        if ($this->step->completed_at) {
            $this->step->progress()->delete();
        }
    }

    private function checkTestResults(): void
    {
        if (! $this->entry) {
            $this->testPassed = false;

            return;
        }

        $grade = $this->test->gradeEntry($this->entry);

        // Check if grade is an Exception (error)
        if ($grade instanceof \Exception) {
            $this->testPassed = false;

            return;
        }

        // If require_perfect_score is enabled, test passes only if 100% correct
        // Otherwise, any score passes
        if ($this->step->require_perfect_score) {
            $this->testPassed = $grade === 100.0;
        } else {
            $this->testPassed = true; // Allow any score to pass
        }
    }
}
