@php
    use Illuminate\Support\Facades\Auth;
    use Tapp\FilamentLms\Services\ScormProgressService;

    /** @var \Tapp\FilamentLms\Models\Course $course */
    $user = Auth::user();
    $canComplete = $user
        ? app(ScormProgressService::class)->userMayConfirmCourseCompletion($course, $user)
        : false;
@endphp
@include('filament-lms::components.html5-exit-completion-script')
<script>
(function () {
    const commitUrl = @json($commitUrl);
    const csrfToken = @json(csrf_token());

    document.body.dataset.lmsCanComplete = @json($canComplete ? '1' : '0');

    function postProgress() {
        fetch(commitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ html5_progress: true }),
        }).catch(() => {});
    }

    function completeCourse() {
        fetch(commitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ lesson_status: 'completed', html5_complete: true }),
        }).then((response) => {
            if (! response.ok) {
                return;
            }

            window.dispatchEvent(new CustomEvent('scorm-course-complete'));
        }).catch(() => {});
    }

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) {
            return;
        }
        const data = event.data;
        if (!data || data.type !== 'lms-html5-progress') {
            return;
        }
        if (data.status === 'progress') {
            postProgress();
        }
        if (data.status === 'completed') {
            completeCourse();
        }
    });
})();
</script>
