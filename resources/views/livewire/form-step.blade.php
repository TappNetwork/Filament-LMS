<div>
    @php($isEvaluationCourse = $step->lesson->course->isEvaluationCourse())

    @if($entry)
        @livewire('tapp.filament-form-builder.livewire.filament-form-user.show', [$entry])
    @else
        @livewire('tapp.filament-form-builder.livewire.filament-form.show', [$form, 'blockRedirect' => true, 'allowMultipleSubmissions' => $isEvaluationCourse])
    @endif

    @if(! $isEvaluationCourse)
        <x-filament-lms::next-button :disabled="!$step->is_optional && !$entry" />
    @endif
</div>
