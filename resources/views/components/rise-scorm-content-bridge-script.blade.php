<script data-lms-rise-scorm-content-bridge="1">
(function () {
    const commitUrl = @json($commitUrl);
    const csrfToken = @json(csrf_token());
    let lastReportedLocation = null;

    function postCommit(payload) {
        return fetch(commitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).catch(() => null);
    }

    function currentContentLocation() {
        const href = window.location.href;
        const marker = '/scormcontent/';
        const idx = href.toLowerCase().indexOf(marker);

        if (idx < 0) {
            return null;
        }

        return decodeURIComponent(href.substring(idx + marker.length));
    }

    function reportLocation() {
        const location = currentContentLocation();

        if (location === null || location === '' || location === lastReportedLocation) {
            return;
        }

        lastReportedLocation = location;

        postCommit({
            lesson_status: 'incomplete',
            lesson_location: location,
        });
    }

    reportLocation();
    window.addEventListener('hashchange', reportLocation);
    window.addEventListener('popstate', reportLocation);
    window.setInterval(reportLocation, 2000);
})();
</script>
