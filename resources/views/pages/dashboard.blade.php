<x-filament-panels::page>
    @foreach ($courses as $course)
        <a href="{{ $course->linkToCurrentStep() }}">
            {{ $course->name }}
        </a>
    @endforeach
</x-filament-panels::page>
