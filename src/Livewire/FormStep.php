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

    public bool $isEvaluationCourse = false;

    public function mount($step, ?int $evaluationPrimaryCourseId = null): void
    {
        $this->step = $step;
        $this->form = $step->material;
        $this->seconds = $step->seconds ?? 0;
        $this->evaluationPrimaryCourseId = $evaluationPrimaryCourseId;
        $this->isEvaluationCourse = $step->lesson->course->isEvaluationCourse();
        $this->entry = $step->formEntryForUser(Auth::id(), $evaluationPrimaryCourseId);

        $this->resetErrorBag();
    }

    public function render()
    {
        return view('filament-lms::livewire.form-step');
    }

    /**
     * @return array{form: FilamentForm, blockRedirect: bool, allowMultipleSubmissions: bool}
     */
    public function formComponentParameters(): array
    {
        return [
            'form' => $this->form,
            'blockRedirect' => true,
            'allowMultipleSubmissions' => $this->isEvaluationCourse,
        ];
    }

    public function shouldShowNextButton(): bool
    {
        return ! $this->isEvaluationCourse;
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

        if ($this->isEvaluationCourse) {
            $this->dispatch('complete-step', completeStep: false);
        }
    }
}
