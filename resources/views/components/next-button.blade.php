@props([
    'disabled' => false,
    'fixed' => true,
])

@if($fixed)
    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button 
            color="primary" 
            size="xl"
            wire:click="$dispatch('complete-step')" 
            :disabled="$disabled" 
            class="next-button"
        >
            Next
        </x-filament::button>
    </div>
@else
    <x-filament::button 
        color="primary" 
        size="xl"
        wire:click="$dispatch('complete-step')" 
        :disabled="$disabled" 
        class="next-button"
    >
        Next
    </x-filament::button>
@endif
