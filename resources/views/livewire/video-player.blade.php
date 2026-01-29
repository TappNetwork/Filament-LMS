<div>
    <div class="vidstack-player-custom" wire:ignore id="target"></div>
    <p class="text-sm text-gray-600 text-center mt-2">
        Click to play/pause • Double click for fullscreen
    </p> 
</div>

@assets
<link rel="stylesheet" href="https://cdn.vidstack.io/player/theme.css" />
<link rel="stylesheet" href="https://cdn.vidstack.io/player/video.css" />

<style>
    .vidstack-player-custom {
        height: 60vh;
        max-width: calc(60vh * 16/9);
        margin: 0 auto;
    }

    @if (! auth()->user()->is_admin)
    .vds-controls {
        display: none;
    }
    @endif
</style>
@endassets

@script
<script>
 let completed = {{ $step->completed_at ? 1 : 0 }};
 let lastTime = {{ $step->seconds ?: 0 }};

const player = await VidstackPlayer.create({
     target: '#target',
     src: '{{$video->url}}',
     viewType: 'video',
     streamType: 'on-demand',
     logLevel: 'warn',
     crossOrigin: true,
     playsInline: true,
     layout: new VidstackPlayerLayout({
        disableTimeSlider: true,
     }),
 });

 // Ensure the video starts at the correct time after loading
 player.subscribe(({canPlay}) => {
     if (canPlay) {
         player.currentTime = lastTime;
     }
 });

 // events
 player.subscribe(({currentTime, ended}) => {
     const rounded = Math.round(currentTime);
     if (!completed && rounded > lastTime && rounded % 10 === 0) {
         lastTime = rounded;
         $wire.videoProgress(rounded);
     } else if (ended) {
         $wire.videoEnded();
     }
 });

</script>
@endscript
