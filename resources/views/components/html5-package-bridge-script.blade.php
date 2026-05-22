<script>
(function () {
    const origin = window.location.origin;
    let lastSlideIndex = null;

    function notifyProgress() {
        window.parent.postMessage({ type: 'lms-html5-progress', status: 'progress' }, origin);
    }

    function notifyCompleted() {
        window.parent.postMessage({ type: 'lms-html5-progress', status: 'completed' }, origin);
    }

    function tryHookPlayer() {
        try {
            if (typeof window.GetPlayer === 'function') {
                const player = window.GetPlayer();
                if (player && typeof player.GetCurrentSlideIndex === 'function' && typeof player.GetSlideCount === 'function') {
                    const index = player.GetCurrentSlideIndex();
                    const count = player.GetSlideCount();

                    if (lastSlideIndex !== null && index !== lastSlideIndex) {
                        notifyProgress();
                    }

                    lastSlideIndex = index;

                    if (count > 0 && index >= count - 1) {
                        notifyCompleted();
                    }
                }
            }
        } catch (e) {
            // Player API not available in this export.
        }
    }

    window.addEventListener('beforeunload', function () {
        tryHookPlayer();
    });

    setInterval(tryHookPlayer, 10000);
})();
</script>
