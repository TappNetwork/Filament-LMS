<div>
    @if ($video->provider == 'vimeo')
        <livewire:vimeo-video :step="$step" :video="$video"/>
    @elseif ($video->provider == 'youtube')
        <livewire:video-player :step="$step" :video="$video"/>
    @elseif ($video->provider == 'external')
        <x-filament::section
            icon="heroicon-o-arrow-top-right-on-square"
            icon-color="primary"
        >
            <p class="mb-6">
                This video is not embedded here. Open the link below to watch it in your browser.
            </p>

            <x-filament::button
                wire:click="markExternalOpened"
                href="{{ $video->url }}"
                rel="noopener noreferrer"
                target="_blank"
                tag="a"
            >
                Open video
            </x-filament::button>
        </x-filament::section>
    @else
        Video provider "{{ $video->provider }}" not supported.
    @endif

    <x-filament-lms::next-button :disabled="!$step->is_optional && !$videoCompleted" />
</div>
