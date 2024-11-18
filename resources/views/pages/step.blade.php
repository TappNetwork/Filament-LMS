<x-filament-lms::course-layout :course="$course">
    @if ($step->material_type == 'video')
        <livewire:video-step :step="$step"/>
    @endif
</x-filament-lms::course-layout>
