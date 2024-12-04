<x-filament-panels::page>
    <div class="grid grid-cols-3 gap-y-4 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-10 lg:grid-cols-3 lg:gap-x-8">
        @foreach ($courses as $course)
            <x-filament-lms::course-card :course="$course"/>
        @endforeach
    </div>
</x-filament-panels::page>
