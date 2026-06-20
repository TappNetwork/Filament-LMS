<div class="fb-form-component filament-form-builder lms-test-form-wrapper">
    <div class="w-full fb-form-container">
        <h1 class="mb-2 text-xl font-bold">
            {{ $this->filamentForm->name }}
        </h1>
        @if ($this->filamentForm->description)
            <div class="mb-4 prose prose-sm max-w-none dark:prose-invert">
                {{-- Description is admin-controlled rich text (HTML) --}}
                {!! $this->filamentForm->description !!}
            </div>
        @endif
        <form wire:submit="create">
            @csrf
            {{ $this->form }}

            <x-filament::button type="submit" class="mt-6">
                Submit
            </x-filament::button>
        </form>

        <x-filament-actions::modals />
    </div>
</div>
