@php
    use Illuminate\Support\Facades\Auth;
    use Tapp\FilamentLms\Enums\CompletionMode;
    use Tapp\FilamentLms\Models\Course;
    use Tapp\FilamentLms\Services\ScormProgressService;

    $courseSlug = request()->route('courseSlug');
    $course = is_string($courseSlug)
        ? Course::query()->where('slug', $courseSlug)->first()
        : null;
    $progressService = app(ScormProgressService::class);
    $user = Auth::user();
    $needsConfirm = $course
        && $course->isEmbeddedPlayer()
        && $course->completionMode() === CompletionMode::Html5
        && $user
        && ! $progressService->courseCompletedByUser($course, $user);
    $canComplete = $needsConfirm && $user
        ? $progressService->userMayConfirmCourseCompletion($course, $user)
        : false;
    $commitUrl = $needsConfirm
        ? route('filament-lms.scorm-commit.store', ['course' => $course])
        : null;
@endphp

@if ($needsConfirm)
    @include('filament-lms::components.html5-exit-completion-script')
    <x-filament::link
        color="gray"
        href="/"
        tag="a"
        id="lms-exit-with-complete"
        data-commit-url="{{ $commitUrl }}"
        data-csrf-token="{{ csrf_token() }}"
    >
        Exit LMS
    </x-filament::link>
    <script>
        document.body.dataset.lmsCanComplete = @json($canComplete ? '1' : '0');

        document.getElementById('lms-exit-with-complete')?.addEventListener('click', function (event) {
            event.preventDefault();
            if (typeof window.lmsConfirmHtml5CourseComplete !== 'function' || ! window.lmsConfirmHtml5CourseComplete()) {
                window.location.href = '/';

                return;
            }
            window.lmsPostHtml5CourseComplete(this.dataset.commitUrl, this.dataset.csrfToken)
                .then(() => {
                    window.location.href = '/';
                })
                .catch(() => {
                    window.location.href = '/';
                });
        });
    </script>
@else
    <x-filament::link color="gray" href="/">
        Exit LMS
    </x-filament::link>
@endif
