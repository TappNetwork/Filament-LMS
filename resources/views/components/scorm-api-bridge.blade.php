@php
    /** @var \Tapp\FilamentLms\Models\Course $course */
@endphp
<script>
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

        fetch(commitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).catch(() => {});
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
            if (element === 'cmi.core.lesson_status' || element === 'cmi.core.lesson_location') {
                postCommit();
            }
            return 'true';
        }),
        LMSCommit: apiMethod('LMSCommit', function () {
            postCommit();
            return 'true';
        }),
        LMSGetLastError: function () { return '0'; },
        LMSGetErrorString: function () { return 'No error'; },
        LMSGetDiagnostic: function () { return 'No error'; },
    };
})();
</script>
