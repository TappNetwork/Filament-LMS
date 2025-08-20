<div>
    @if ($video->provider == 'vimeo')
        <livewire:vimeo-video :step="$step" :video="$video"/>
    @elseif ($video->provider == 'youtube')
        <livewire:video-player :step="$step" :video="$video"/>
    @else
        Video provider "{{ $video->provider }}" not supported.
    @endif

    <div class="fixed bottom-0 right-0 p-4 md:p-6 lg:p-8">
        <x-filament::button color="gray" wire:click="$dispatch('complete-step')" :disabled="!$videoCompleted" class="next-button">
            Next
        </x-filament::button>
    </div>
</div>
