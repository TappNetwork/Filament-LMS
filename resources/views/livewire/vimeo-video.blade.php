<div class="step-material-container">
    <!-- https://codepen.io/danfascia/pen/zYxXXXM?editors=1011 -->
    <iframe
        class="bg-black w-full h-full"
        src="{{$video->url}}"
        width="800"
        height="450"
        frameborder="0"
        webkitallowfullscreen
        mozallowfullscreen
        allowfullscreen
    ></iframe>
</div>

@script
<script>
 const iframe = document.querySelector('iframe');
 const player = new Vimeo(iframe);
 const completed = {{ $step->completed_at ? 1 : 0 }};
 const seconds = {{ $step->seconds ?: 0 }};

 // setup
 if (!completed && seconds > 0) {
     player.setCurrentTime(seconds);
 }

 player.play();

 // events
 player.on('timeupdate', time => {
     const rounded = Math.round(time.seconds);

     if (rounded && rounded % 10 === 0) {
         $wire.videoProgress(rounded);
     }
 })

 player.on('ended', () => {
     $wire.videoEnded();
 })
</script>
@endscript
