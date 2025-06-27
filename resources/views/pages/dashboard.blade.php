<x-filament-panels::page>
    @if ($courses->isEmpty())
        <x-filament::card>
            <div class="flex flex-col items-center justify-center py-12">
                <span class="text-lg text-gray-500">Courses will be made available to you soon.</span>
            </div>
        </x-filament::card>
    @else
        <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2 sm:gap-x-6 sm:gap-y-10 lg:grid-cols-3 lg:gap-x-8">
            @foreach ($courses as $course)
                <x-filament-lms::course-card :course="$course"/>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
