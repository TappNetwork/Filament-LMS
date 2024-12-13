<div>
    @if($entry)
        <x-filament::section>
            <h1 class="text-2xl">
                Thank you for completing the form: {{ $form->name }}
            </h1>
        </x-filament::section>
    @else
        @livewire('tapp.filament-form-builder.livewire.filament-form.show', [$form, 'blockRedirect' => true])
    @endif

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button size="xl" wire:click="$dispatch('complete-step')" :disabled="!$entry">
            Next
        </x-filament::button>
    </div>
</div>
