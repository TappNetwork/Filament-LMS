<div>
    @if($entry)
        @livewire('tapp.filament-form-builder.livewire.filament-form-user.show', [$entry])
    @else
        @livewire('tapp.filament-form-builder.livewire.filament-form.show', [$form, 'blockRedirect' => true])
    @endif

    @if(! $step->lesson->course->isEvaluationCourse())
        <x-filament-lms::next-button :disabled="!$step->is_optional && !$entry" />
    @endif
</div>
