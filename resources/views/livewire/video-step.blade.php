<div>
    @if ($video->provider == 'vimeo')
        <livewire:vimeo-video :step="$step" :video="$video"/>
    @elseif ($video->provider == 'youtube')
        <livewire:video-player :step="$step" :video="$video"/>
    @else
        Video provider "{{ $video->provider }}" not supported.
    @endif

    <x-filament-lms::next-button :disabled="!$step->is_optional && !$videoCompleted" />
</div>
