<div>
@if($showResults)
    <x-filament::section>
        <h1 class="text-2xl">
            Thank you for completing the test: {{ $test->name }}
        </h1>
        <h4 class="text-xl mt-4">
            You correctly answered {{ $questionsCorrect }} out of {{ $test->form->filamentFormFields->count() }} questions.
        </h4>
        <h3 class="text-xl">
            {{ $percentageCorrect }}% Correct
        </h3>
        @if($entry)
            <div class="mt-4">
                @livewire('view-graded-entry', ['test' => $test, 'entry' => $entry])
            </div>
        @endif
    </x-filament::section>
@else
    @livewire('tapp.filament-form-builder.livewire.filament-form.show', ['form' => $test->form, 'blockRedirect' => true])
@endif 
</div>