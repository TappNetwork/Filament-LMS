<div>
    @if($entry)
        @livewire('tapp.filament-form-builder.livewire.filament-form-user.show', [$entry])
    @else
        @livewire('tapp.filament-form-builder.livewire.filament-form.show', [$form, 'blockRedirect' => true])
    @endif

    <x-filament-lms::next-button :disabled="!$entry" />
</div>
