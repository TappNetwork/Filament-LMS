<div>
    <x-filament::section
        icon="heroicon-o-link"
        icon-color="primary"
        >
            <x-slot name="heading">
                External Link
            </x-slot>

            <p class="mb-8">
                In order to complete this step, please review the following web page:
            </p>

            @if($link->getFirstMediaUrl('preview'))
                <div class="mb-8 flex-1">
                    <img 
                        src="{{ $link->getFirstMediaUrl('preview') }}" 
                        class="w-full rounded-lg border border-gray-300"
                        style="min-height: 50vh; object-fit: contain;"
                        alt="Preview of {{ $link->name }}"
                    >
                </div>
            @endif

            <x-filament::button
                wire:click="visit"
                href="{{$link->url}}"
                rel="noopener noreferrer"
                target="_blank"
                tag="a"
                >
                Visit
            </x-filament::button>
    </x-filament::section>

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="gray" size="xl" wire:click="$dispatch('complete-step')" :disabled="! $visited">
            Next
        </x-filament::button>
    </div>
</div>
