<div>
    <div class="py-5">
        <div class="sm:flex sm:items-center sm:justify-between">
                <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl" >
                {{ $step->name }}
            </h1>
            <div>
                <a href="{{\Tapp\FilamentLms\Pages\Dashboard::getUrl()}}">
                    <x-filament::button color="gray">
                        View All Courses
                    </x-filament::button>
                </a>
            </div>
        </div>
    </div>

    @if (is_null($step->material))
        <div class="flex items-center justify-center min-h-[60vh]">
            <x-filament::card class="max-w-md w-full py-12">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="text-red-600 text-lg font-semibold mb-4">
                        The material for this step is missing or has been deleted.
                    </div>
                    <x-filament::button color="primary" size="md" class="w-auto" wire:click="$dispatch('complete-step')">
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
        <div class="flex items-center justify-center min-h-[60vh]">
            <x-filament::card class="max-w-md w-full py-12">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="text-red-600 text-lg font-semibold mb-4">
                        Unsupported material type: {{ $step->material_type }}
                    </div>
                    <x-filament::button color="primary" size="md" class="w-auto" wire:click="$dispatch('complete-step')">
                        Next
                    </x-filament::button>
                </div>
            </x-filament::card>
        </div>
    @endif
</div>
