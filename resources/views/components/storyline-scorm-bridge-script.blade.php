<script data-lms-storyline-scorm-bridge="1">
(function () {
    const commitUrl = @json($commitUrl);
    const csrfToken = @json(csrf_token());
    let lastReportedLocation = null;
    let lastReportedSuspendData = null;
    let cachedSlideHost = null;
    let cachedLoadTracker = null;

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

    function hasProgressPayload(location, suspendData) {
        return (location !== null && location !== '')
            || (suspendData !== null && suspendData !== '');
    }

    function reportProgress(extra = {}) {
        const location = extra.lesson_location ?? lastReportedLocation;
        const suspendData = extra.suspend_data ?? lastReportedSuspendData;

        if (! hasProgressPayload(location, suspendData)) {
            return;
        }

        postCommit({
            lesson_status: 'incomplete',
            lesson_location: location || null,
            suspend_data: suspendData || null,
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

    function findObjectWithMethod(root, methodName, maxDepth, seen, depth) {
        if (root === null || root === undefined || depth > maxDepth) {
            return null;
        }

        if (typeof root !== 'object' && typeof root !== 'function') {
            return null;
        }

        if (typeof seen === 'undefined') {
            seen = new WeakSet();
        }

        if (depth === undefined) {
            depth = 0;
        }

        if (seen.has(root)) {
            return null;
        }

        seen.add(root);

        if (typeof root[methodName] === 'function') {
            return root;
        }

        const keys = [];

        try {
            keys.push(...Object.keys(root));
        } catch (e) {
            return null;
        }

        for (let i = 0; i < keys.length; i++) {
            try {
                const found = findObjectWithMethod(root[keys[i]], methodName, maxDepth, seen, depth + 1);

                if (found !== null) {
                    return found;
                }
            } catch (e) {
                // Ignore inaccessible properties.
            }
        }

        return null;
    }

    function findLoadTracker(root, maxDepth, seen, depth) {
        if (root === null || root === undefined || (depth !== undefined && depth > maxDepth)) {
            return null;
        }

        if (typeof root !== 'object') {
            return null;
        }

        if (typeof seen === 'undefined') {
            seen = new WeakSet();
        }

        if (depth === undefined) {
            depth = 0;
        }

        if (seen.has(root)) {
            return null;
        }

        seen.add(root);

        if (root.loadTracker && typeof root.loadTracker === 'object') {
            const keys = Object.keys(root.loadTracker);

            if (keys.length > 0) {
                return root.loadTracker;
            }
        }

        const keys = [];

        try {
            keys.push(...Object.keys(root));
        } catch (e) {
            return null;
        }

        for (let i = 0; i < keys.length; i++) {
            try {
                const found = findLoadTracker(root[keys[i]], maxDepth, seen, depth + 1);

                if (found !== null) {
                    return found;
                }
            } catch (e) {
                // Ignore inaccessible properties.
            }
        }

        return null;
    }

    function resolveSlideHost() {
        if (cachedSlideHost !== null) {
            return cachedSlideHost;
        }

        const directCandidates = [
            window.DS && window.DS.frame,
            window.DS && window.DS.player,
            window.DS && window.DS.views && window.DS.views.nsStack && window.DS.views.nsStack[0],
            window.vInterfaceObject,
            window.player,
        ];

        for (let i = 0; i < directCandidates.length; i++) {
            const candidate = directCandidates[i];

            if (candidate && typeof candidate.getCurrentWindowSlide === 'function') {
                cachedSlideHost = candidate;

                return cachedSlideHost;
            }
        }

        cachedSlideHost = findObjectWithMethod(window.DS, 'getCurrentWindowSlide', 6)
            || findObjectWithMethod(window, 'getCurrentWindowSlide', 4);

        return cachedSlideHost;
    }

    function resolveLoadTracker() {
        if (cachedLoadTracker !== null) {
            return cachedLoadTracker;
        }

        cachedLoadTracker = findLoadTracker(window.DS, 8) || findLoadTracker(window, 5);

        return cachedLoadTracker;
    }

    function slideReferenceFromSlide(slide) {
        if (! slide) {
            return null;
        }

        if (slide.absoluteId) {
            return String(slide.absoluteId);
        }

        if (slide.id) {
            return String(slide.id);
        }

        return null;
    }

    function resolveCurrentSlideReference() {
        try {
            const host = resolveSlideHost();

            if (host) {
                return slideReferenceFromSlide(host.getCurrentWindowSlide());
            }
        } catch (e) {
            cachedSlideHost = null;
        }

        return null;
    }

    function suspendDataFromLoadTracker() {
        try {
            const tracker = resolveLoadTracker();

            if (! tracker) {
                return null;
            }

            const keys = Object.keys(tracker).filter(function (key) {
                return key !== null && key !== '';
            });

            if (keys.length === 0) {
                return null;
            }

            return keys.join('|');
        } catch (e) {
            cachedLoadTracker = null;
        }

        return null;
    }

    function wrapDriverFunction(name, handler) {
        const intervalId = window.setInterval(function () {
            const original = window[name];

            if (typeof original !== 'function') {
                return;
            }

            window.clearInterval(intervalId);

            window[name] = function () {
                handler.apply(this, arguments);

                return original.apply(this, arguments);
            };
        }, 50);
    }

    wrapDriverFunction('SetBookmark', function (bookmark) {
        reportLocation(bookmark);
    });

    wrapDriverFunction('SetDataChunk', function (data) {
        reportSuspendData(data);
    });

    wrapDriverFunction('SCORM_CallLMSSetValue', function (element, value) {
        const normalizedElement = String(element ?? '');

        if (normalizedElement === 'cmi.core.lesson_location') {
            reportLocation(value);
        } else if (normalizedElement === 'cmi.suspend_data') {
            reportSuspendData(value);
        }
    });

    wrapDriverFunction('CommitData', function () {
        syncPlayerProgress();
    });

    function readBookmarkFromDriver() {
        try {
            if (typeof window.GetBookmark === 'function') {
                reportLocation(window.GetBookmark());
            }
        } catch (e) {
            // Driver not ready yet.
        }
    }

    function readSuspendDataFromDriver() {
        try {
            if (typeof window.GetDataChunk === 'function') {
                reportSuspendData(window.GetDataChunk());
            }
        } catch (e) {
            // Driver not ready yet.
        }
    }

    function syncPlayerProgress() {
        const slideReference = resolveCurrentSlideReference();

        if (slideReference) {
            reportLocation(slideReference);
        }

        const trackerSuspendData = suspendDataFromLoadTracker();

        if (trackerSuspendData) {
            reportSuspendData(trackerSuspendData);
        } else {
            readBookmarkFromDriver();
            readSuspendDataFromDriver();
        }
    }

    window.setInterval(syncPlayerProgress, 1500);
    window.addEventListener('beforeunload', function () {
        syncPlayerProgress();

        if (hasProgressPayload(lastReportedLocation, lastReportedSuspendData)) {
            reportProgress();
        }
    });
})();
</script>
