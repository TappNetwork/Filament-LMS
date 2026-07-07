<div>
    @if($entry)
        @livewire('tapp.filament-form-builder.livewire.filament-form-user.show', [$entry])
    @else
        @livewire('tapp.filament-form-builder.livewire.filament-form.show', $this->formComponentParameters())
    @endif

    @if($this->shouldShowNextButton())
        <x-filament-lms::next-button :disabled="!$step->is_optional && !$entry" />
    @endif
</div>
