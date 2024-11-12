<x-filament-lms::course-layout :course="$course">
    Congratulations! You have completed "{{ $course->name }}".
    <button wire:click="complete">Download Certificate</button>
</x-filament-lms::course-layout>
