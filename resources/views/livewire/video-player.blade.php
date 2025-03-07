<div>
    <div style="max-height:80vh; max-width:160vh" wire:ignore id="target"></div>
</div>

@assets
<link rel="stylesheet" href="https://cdn.vidstack.io/player/theme.css" />
<link rel="stylesheet" href="https://cdn.vidstack.io/player/video.css" />
@endassets

@script
<script>
 let completed = {{ $step->completed_at ? 1 : 0 }};

const player = await VidstackPlayer.create({
     target: '#target',
     src: '{{$video->url}}',
     viewType: 'video',
     streamType: 'on-demand',
     logLevel: 'warn',
     crossOrigin: true,
     playsInline: true,
     // title: 'Sprite Fight',
     layout: new VidstackPlayerLayout(),
 });

 // events
 player.subscribe(({currentTime}) => {
     if (!completed && currentTime > 0) {
         $wire.videoEnded()
     }
 })

</script>
@endscript
