<x-filament-panels::page>
    @if($step->text)
        @if(is_null($step->material_type))
            {{-- Text-only step: fi-prose styles markdown output (headings, bold, lists, etc.) --}}
            <x-filament::section class="max-w-3xl mx-auto">
                <div class="fi-prose">
                    {!! \Illuminate\Support\Str::markdown($step->text) !!}
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="fi-prose">
                    {!! \Illuminate\Support\Str::markdown($step->text) !!}
                </div>
            </x-filament::section>
        @endif
    @endif

    @if (is_null($step->material_type))
        {{-- Intentionally text-only step: show the next button --}}
        <x-filament-lms::next-button />
    @elseif (is_null($step->material))
        {{-- Material type is set but material is missing (deleted): show error --}}
        <div class="flex items-center justify-center min-h-[60vh]">
            <x-filament::card class="py-12 w-full max-w-md">
                <div class="flex flex-col justify-center items-center text-center">
                    <div class="mb-4 text-lg font-semibold text-red-600">
                        The material for this step is missing or has been deleted.
                    </div>
                    <x-filament-lms::next-button :fixed="false" />
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
