<div>
    @if($entry)
        @livewire('view-graded-entry', ['test' => $test, 'entry' => $entry])
    @else
        @livewire('create-test-entry', ['test' => $test])
    @endif

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="gray" size="xl" wire:click="$dispatch('complete-step')" :disabled="!$entry" class="next-button">
            Next
        </x-filament::button>
    </div>
</div> 