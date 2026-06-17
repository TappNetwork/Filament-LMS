<script>
(function () {
    const origin = window.location.origin;
    let lastSlideIndex = null;
    let hasSeenSlideChange = false;

    function notifyProgress() {
        window.parent.postMessage({ type: 'lms-html5-progress', status: 'progress' }, origin);
    }

    function notifyCompleted() {
        window.parent.postMessage({ type: 'lms-html5-progress', status: 'completed' }, origin);
    }

    function tryHookPlayer(isBeforeUnload) {
        try {
            if (typeof window.GetPlayer === 'function') {
                const player = window.GetPlayer();
                if (player && typeof player.GetCurrentSlideIndex === 'function' && typeof player.GetSlideCount === 'function') {
                    const index = player.GetCurrentSlideIndex();
                    const count = player.GetSlideCount();

                    if (lastSlideIndex !== null && index !== lastSlideIndex) {
                        notifyProgress();
                        hasSeenSlideChange = true;
                    }

                    lastSlideIndex = index;

                    if (count > 1 && index >= count - 1 && hasSeenSlideChange) {
                        notifyCompleted();
                    } else if (count === 1 && isBeforeUnload) {
                        notifyCompleted();
                    }
                }
            }
        } catch (e) {
            // Player API not available in this export.
        }
    }

    window.addEventListener('beforeunload', function () {
        tryHookPlayer(true);
    });

    setInterval(function () {
        tryHookPlayer(false);
    }, 10000);
})();
</script>
