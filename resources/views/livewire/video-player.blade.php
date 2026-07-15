<div>
    <div class="vidstack-player-custom" wire:ignore id="target"></div>
    <p class="text-sm text-gray-600 text-center mt-2">
        Tap to play/pause
    </p>
</div>

@assets
<link rel="stylesheet" href="https://cdn.vidstack.io/player/theme.css" />
<link rel="stylesheet" href="https://cdn.vidstack.io/player/video.css" />

<style>
    .vidstack-player-custom {
        display: block;
        width: min(100%, calc(60vh * 16 / 9));
        margin: 0 auto;
    }

    .vidstack-player-custom media-player {
        width: 100%;
        aspect-ratio: 16 / 9;
        contain: layout;
        overflow: visible;
    }

    /*
     * On mobile, the YouTube iframe paints above HTML overlays, so our Vidstack
     * play button stacks on top of YouTube's native one. Until playback starts,
     * hide Vidstack chrome/blocker so the learner only sees YouTube's play.
     * After start, Vidstack controls take over (YouTube embed controls stay off).
     */
    .vidstack-player-custom media-player:not([data-started]) .vds-blocker,
    .vidstack-player-custom media-player:not([data-started]) .vds-controls,
    .vidstack-player-custom media-player:not([data-started]) .vds-gestures,
    .vidstack-player-custom media-player:not([data-started]) .vds-buffering-indicator {
        display: none !important;
        pointer-events: none !important;
    }

    /* Vidstack small layout hangs the bottom bar slightly outside the player; keep it in frame. */
    .vidstack-player-custom .vds-video-layout[data-sm] .vds-controls-group:nth-last-child(2),
    .vidstack-player-custom .vds-video-layout[data-sm] .vds-controls-group:last-child {
        margin-bottom: 0 !important;
        margin-top: 0 !important;
    }

    @if (! auth()->user()->is_admin)
    /* Keep play/fullscreen controls; hide seek UI so learners cannot skip ahead. */
    .vidstack-player-custom .vds-seek-button,
    .vidstack-player-custom .vds-time-slider {
        display: none !important;
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
        noScrubGesture: {{ auth()->user()->is_admin ? 'false' : 'true' }},
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
