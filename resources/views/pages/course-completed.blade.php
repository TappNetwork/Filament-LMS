<x-filament::section class="m-8">
    <x-slot name="heading">
    Congratulations! You have completed "{{ $course->name }}"
    </x-slot>

    <x-slot name="headerEnd">
    </x-slot>

    Download your certificate below.

    <div class="mt-4">
    <a href="{{\Tapp\FilamentLms\Pages\Dashboard::getUrl()}}">
        <x-filament::button color="gray">
            View All Courses
        </x-filament::button>
    </a>

    <x-filament::button tag="a" href="{{route('filament-lms::certificates.download', [$this->course->id])}}">
        Download Certificate
    </x-filament::button>
    </div>
</x-filament::section>
