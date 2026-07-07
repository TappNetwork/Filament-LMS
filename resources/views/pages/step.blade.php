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
    @elseif (in_array($step->material_type, ['library_file', 'library_link'], true) && ! class_exists('Tapp\FilamentLibrary\Models\LibraryItem'))
        {{-- Library morph types cannot be resolved without filament-library; avoid loading material() (fatal). --}}
        <div class="flex items-center justify-center min-h-[60vh]">
            <x-filament::card class="py-12 w-full max-w-md">
                <div class="flex flex-col justify-center items-center text-center">
                    <div class="mb-4 text-lg font-semibold text-red-600">
                        This step uses library content, but the library package is not installed or unavailable.
                    </div>
                    <x-filament-lms::next-button :fixed="false" />
                </div>
            </x-filament::card>
        </div>
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
        <livewire:video-step :step="$step" :evaluation-primary-course-id="$evaluationPrimaryCourseId"/>
    @elseif ($step->material_type == 'form')
        <livewire:form-step :step="$step" :evaluation-primary-course-id="$evaluationPrimaryCourseId"/>
    @elseif ($step->material_type == 'document')
        <livewire:document-step :step="$step"/>
    @elseif ($step->material_type == 'link')
        <livewire:link-step :step="$step"/>
    @elseif ($step->material_type == 'test')
        <livewire:test-step :step="$step" :evaluation-primary-course-id="$evaluationPrimaryCourseId"/>
    @elseif ($step->material_type == 'image')
        <livewire:image-step :step="$step"/>
    @elseif ($step->material_type == 'library_file')
        <livewire:library-file-step :step="$step"/>
    @elseif ($step->material_type == 'library_link')
        <livewire:library-link-step :step="$step"/>
    @else
        unsupported material type: {{ $step->material_type }}
    @endif
</x-filament-panels::page>
