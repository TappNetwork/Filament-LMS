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
        background: #000;
    }

    /* Vidstack small layout hangs the bottom bar slightly outside the player; keep it in frame. */
    .vidstack-player-custom .vds-video-layout[data-sm] .vds-controls-group:nth-last-child(2),
    .vidstack-player-custom .vds-video-layout[data-sm] .vds-controls-group:last-child {
        margin-bottom: 0 !important;
        margin-top: 0 !important;
    }

    /* Title duplicates the LMS step heading; hide the in-player overlay. */
    .vidstack-player-custom .vds-chapter-title {
        display: none !important;
    }

    /*
     * Keep the control bar reachable. Default idle hide forces a second tap
     * on touch (first tap only re-shows controls).
     */
    .vidstack-player-custom .vds-controls {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
    }

    /*
     * On touch, Vidstack hides press-to-pause and uses press-to-toggle-controls.
     * Prefer pause so "Tap to play/pause" is one tap.
     */
    @media (pointer: coarse) {
        .vidstack-player-custom .vds-gesture[action='toggle:paused'] {
            display: block !important;
        }

        .vidstack-player-custom .vds-gesture[action='toggle:controls'] {
            display: none !important;
        }
    }

    /*
     * YouTube's iframe still paints its own centered play/pause chrome.
     * Hide the iframe until playback is actively running so only Vidstack's
     * control is visible (poster/black background remains underneath).
     */
    .vidstack-player-custom media-player:not([data-playing]) iframe {
        opacity: 0 !important;
    }

    /* Idle buffering spinner can look like a second centered play icon. */
    .vidstack-player-custom media-player:not([data-started]) .vds-buffering-indicator {
        display: none !important;
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
 let hasSeekedToResumePoint = false;

const player = await VidstackPlayer.create({
     target: '#target',
     title: '',
     viewType: 'video',
     streamType: 'on-demand',
     logLevel: 'warn',
     crossOrigin: true,
     playsInline: true,
     // Keep controls visible — touch otherwise needs a tap just to re-show them.
     controlsDelay: Infinity,
     hideControlsOnMouseLeave: false,
     layout: new VidstackPlayerLayout({
        disableTimeSlider: true,
        noScrubGesture: {{ auth()->user()->is_admin ? 'false' : 'true' }},
     }),
 });

 // Force YouTube captions off by default. They are NOT set in our LMS app —
 // Vidstack's YouTube provider prefers English (`cc_lang_pref=en`) and YouTube
 // may auto-show captions from the video / account. `cc_load_policy=0` opts out.
 const disableYouTubeCaptionsByDefault = (provider) => {
     if (provider?.type !== 'youtube' || typeof provider.buildParams !== 'function') {
         return;
     }

     const originalBuildParams = provider.buildParams.bind(provider);
     provider.buildParams = () => ({
         ...originalBuildParams(),
         cc_load_policy: 0,
     });
 };

 player.addEventListener('provider-change', (event) => {
     disableYouTubeCaptionsByDefault(event.detail);
 });

 // Set src after the provider-change listener so buildParams is patched first.
 player.src = '{{$video->url}}';

 // Resume once after load. Re-seeking on every canPlay can break play/pause on Chromium.
 player.subscribe(({canPlay}) => {
     if (canPlay && !hasSeekedToResumePoint && lastTime > 0) {
         hasSeekedToResumePoint = true;
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
