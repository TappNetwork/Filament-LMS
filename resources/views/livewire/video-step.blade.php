<div>
    @if ($video->provider == 'vimeo')
        <livewire:vimeo-video :step="$step" :video="$video"/>
    @else
        Video provider "{{ $video->provider }}" not supported.
    @endif

    <x-filament::button wire:click="$dispatch('complete-step')" :disabled="!$videoCompleted">
        Next
    </x-filament::button>
</div>
