<?php

namespace Tapp\FilamentLms\Livewire;

use Illuminate\Support\Facades\Auth;
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

    public ?int $evaluationPrimaryCourseId = null;

    public function mount($step, ?int $evaluationPrimaryCourseId = null): void
    {
        $this->step = $step;
        $this->form = $step->material;
        $this->seconds = $step->seconds ?? 0;
        $this->evaluationPrimaryCourseId = $evaluationPrimaryCourseId;
        $this->entry = $step->formEntryForUser(Auth::id(), $evaluationPrimaryCourseId);
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

        $this->step->complete(evaluationPrimaryCourseId: $this->evaluationPrimaryCourseId);

        StepUser::query()
            ->where('user_id', Auth::id())
            ->where('step_id', $this->step->id)
            ->when(
                $this->evaluationPrimaryCourseId !== null,
                fn ($query) => $query->where('evaluation_primary_course_id', $this->evaluationPrimaryCourseId),
                fn ($query) => $query->whereNull('evaluation_primary_course_id'),
            )
            ->update(['filament_form_user_id' => $entry->id]);

        if ($this->step->lesson->course->isEvaluationCourse()) {
            $this->dispatch('complete-step', completeStep: false);
        }
    }
}
