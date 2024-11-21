<x-filament-panels::page>
    @foreach ($courses as $course)
        <x-filament-lms::course-card :course="$course"/>
    @endforeach
</x-filament-panels::page>
