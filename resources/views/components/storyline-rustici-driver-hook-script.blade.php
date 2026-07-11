<script data-lms-storyline-rustici-driver-hook="1">
(function () {
    const commitUrl = @json($commitUrl);
    const csrfToken = @json(csrf_token());
    let lastReportedLocation = null;
    let lastReportedSuspendData = null;

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

    function reportProgress(extra = {}) {
        postCommit({
            lesson_status: 'incomplete',
            lesson_location: lastReportedLocation,
            suspend_data: lastReportedSuspendData,
            ...extra,
        });
    }

    function reportLocation(location) {
        const normalized = String(location ?? '').trim();

        if (normalized === '' || normalized === lastReportedLocation) {
            return;
        }

        lastReportedLocation = normalized;
        reportProgress();
    }

    function reportSuspendData(data) {
        const normalized = String(data ?? '');

        if (normalized === '' || normalized === lastReportedSuspendData) {
            return;
        }

        lastReportedSuspendData = normalized;
        reportProgress();
    }

    function syncFromDriverState() {
        try {
            if (typeof IsLoaded === 'function' && ! IsLoaded()) {
                return;
            }

            if (typeof GetBookmark === 'function') {
                reportLocation(GetBookmark());
            }

            if (typeof GetDataChunk === 'function') {
                reportSuspendData(GetDataChunk());
            }
        } catch (e) {
            // Driver not ready yet.
        }
    }

    function wrapGlobalFunction(name, handler) {
        const intervalId = window.setInterval(function () {
            const original = window[name];

            if (typeof original !== 'function') {
                return;
            }

            window.clearInterval(intervalId);

            window[name] = function () {
                const result = original.apply(this, arguments);
                handler.apply(this, arguments);

                return result;
            };
        }, 50);
    }

    wrapGlobalFunction('SCORM_CallLMSSetValue', function (element, value) {
        const normalizedElement = String(element ?? '');

        if (normalizedElement === 'cmi.core.lesson_location') {
            reportLocation(value);
        } else if (normalizedElement === 'cmi.suspend_data') {
            reportSuspendData(value);
        }
    });

    wrapGlobalFunction('SetBookmark', function (bookmark) {
        reportLocation(bookmark);
    });

    wrapGlobalFunction('SetDataChunk', function (data) {
        reportSuspendData(data);
    });

    wrapGlobalFunction('CommitData', function () {
        syncFromDriverState();
    });

    window.setInterval(syncFromDriverState, 2000);
})();
</script>
