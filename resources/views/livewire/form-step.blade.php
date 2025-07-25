<div>
    @if($entry)
            <div class="mb-8">
        <x-filament::section
    icon="heroicon-o-check"
    icon-color="primary"
>
            Click "Next" (at the bottom of the page) to continue the course.
        </x-filament::section>
            </div>
        @livewire('tapp.filament-form-builder.livewire.filament-form-user.show', [$entry])
    @else
        @livewire('tapp.filament-form-builder.livewire.filament-form.show', [$form, 'blockRedirect' => true])
    @endif

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="gray" size="xl" wire:click="$dispatch('complete-step')" :disabled="!$entry" class="next-button">
            Next
        </x-filament::button>
    </div>
</div>
