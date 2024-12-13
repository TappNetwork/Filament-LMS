<div>
    <div class="py-5">
        <div class="sm:flex sm:items-center sm:justify-between">
            <h3 class="text-xl font-semibold text-gray-900">
                {{ $step->name }}
            </h3>
            <div>
                <a href="{{\Tapp\FilamentLms\Pages\Dashboard::getUrl()}}">
                    <x-filament::button color="gray">
                        View All Courses
                    </x-filament::button>
                </a>
            </div>
        </div>
    </div>

    @if ($step->material_type == 'video')
        <livewire:video-step :step="$step"/>
    @elseif ($step->material_type == 'form')
        <livewire:form-step :step="$step"/>
    @else
        unsupported material type: {{ $step->material_type }}
    @endif
</div>
