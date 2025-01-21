<div>
    <iframe id="target" src="{{$video->url}}"></iframe>
</div>

@assets
<link rel="stylesheet" href="https://cdn.vidstack.io/player/theme.css" />
<link rel="stylesheet" href="https://cdn.vidstack.io/player/video.css" />
@endassets

@script
<script>
    const player = await VidstackPlayer.create({
    target: '#target',
    src: '{{$video->url}}',
    viewType: 'video',
    streamType: 'on-demand',
    logLevel: 'warn',
    crossOrigin: true,
    playsInline: true,
    title: 'Sprite Fight',
        // poster: 'https://files.vidstack.io/sprite-fight/poster.webp',
    layout: new VidstackPlayerLayout({
        // thumbnails: 'https://files.vidstack.io/sprite-fight/thumbnails.vtt',
    }),
    });
</script>
@endscript
