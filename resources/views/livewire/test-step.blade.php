<div>
    @if($entry)
        <div class="mb-8">
            <x-filament::section
                icon="heroicon-o-check"
                icon-color="primary"
            >
                <x-slot name="heading">
                    Test Completed!
                </x-slot>

                Click "Next" (at the bottom of the page) to continue the course.
            </x-filament::section>
        </div>
        @livewire('view-graded-entry', ['test' => $test, 'entry' => $entry])
    @else
        @livewire('create-test-entry', ['test' => $test])
    @endif

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="primary" size="xl" wire:click="$dispatch('complete-step')" :disabled="!$entry">
            Next
        </x-filament::button>
    </div>
</div> 