<x-filament-panels::page>
    @if($step->text)
            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                {{ $step->text }}
            </p>
    @endif

    @if (is_null($step->material))
        <div class="flex items-center justify-center min-h-[60vh]">
            <x-filament::card class="py-12 w-full max-w-md">
                <div class="flex flex-col justify-center items-center text-center">
                    <div class="mb-4 text-lg font-semibold text-red-600">
                        The material for this step is missing or has been deleted.
                    </div>
                    <x-filament::button color="gray" size="md" class="w-auto next-button" wire:click="$dispatch('complete-step')">
                        Next
                    </x-filament::button>
                </div>
            </x-filament::card>
        </div>
    @elseif ($step->material_type == 'video')
        <livewire:video-step :step="$step"/>
    @elseif ($step->material_type == 'form')
        <livewire:form-step :step="$step"/>
    @elseif ($step->material_type == 'document')
        <livewire:document-step :step="$step"/>
    @elseif ($step->material_type == 'link')
        <livewire:link-step :step="$step"/>
    @elseif ($step->material_type == 'test')
        <livewire:test-step :step="$step"/>
    @elseif ($step->material_type == 'image')
        <livewire:image-step :step="$step"/>
    @else
        unsupported material type: {{ $step->material_type }}
    @endif
</x-filament-panels::page>
