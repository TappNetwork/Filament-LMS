<script data-lms-scorm-api-bridge="1">
(function () {
    const commitUrl = @json($commitUrl);
    const csrfToken = @json(csrf_token());
    const store = {
        'cmi.core.lesson_status': 'incomplete',
        'cmi.core.lesson_location': '',
        'cmi.suspend_data': '',
        'cmi.core.score.raw': '',
    };
    let initialized = false;

    function postCommit(extra = {}) {
        const body = {
            lesson_status: store['cmi.core.lesson_status'] || null,
            lesson_location: store['cmi.core.lesson_location'] || null,
            suspend_data: store['cmi.suspend_data'] || null,
            score: store['cmi.core.score.raw'] || null,
            ...extra,
        };

        return fetch(commitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).catch(() => null);
    }

    function maybeDispatchCourseComplete(status) {
        const normalized = String(status ?? '').toLowerCase();

        if (normalized === 'completed' || normalized === 'passed') {
            const origin = window.location.origin;

            if (window.parent !== window) {
                window.parent.postMessage({ type: 'lms-scorm-course-complete' }, origin);
            } else {
                window.dispatchEvent(new CustomEvent('scorm-course-complete'));
            }
        }
    }

    function commitAndMaybeComplete(status) {
        return postCommit().then((response) => {
            if (response?.ok) {
                maybeDispatchCourseComplete(status);
            }
        });
    }

    function apiMethod(name, impl) {
        return function () {
            try {
                return impl.apply(this, arguments);
            } catch (e) {
                return 'false';
            }
        };
    }

    window.IsLmsPresent = function () {
        return true;
    };

    window.API = {
        LMSInitialize: apiMethod('LMSInitialize', function () {
            initialized = true;
            postCommit({ initialized: true });
            return 'true';
        }),
        LMSFinish: apiMethod('LMSFinish', function () {
            postCommit({ finished: true });
            return 'true';
        }),
        LMSGetValue: apiMethod('LMSGetValue', function (element) {
            return store[element] ?? '';
        }),
        LMSSetValue: apiMethod('LMSSetValue', function (element, value) {
            store[element] = String(value ?? '');
            if (element === 'cmi.core.lesson_status') {
                commitAndMaybeComplete(value);
            } else if (element === 'cmi.core.lesson_location' || element === 'cmi.suspend_data') {
                postCommit();
            }
            return 'true';
        }),
        LMSCommit: apiMethod('LMSCommit', function () {
            if (store['cmi.core.lesson_location'] || store['cmi.suspend_data']) {
                commitAndMaybeComplete(store['cmi.core.lesson_status']);
            }

            return 'true';
        }),
        LMSGetLastError: function () { return '0'; },
        LMSGetErrorString: function () { return 'No error'; },
        LMSGetDiagnostic: function () { return 'No error'; },
    };
})();
</script>
