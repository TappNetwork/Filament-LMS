@php
    /** @var \Tapp\FilamentLms\Models\Course $course */
@endphp
@include('filament-lms::components.scorm-api-bridge-script', ['commitUrl' => $commitUrl])
<script>
(function () {
    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) {
            return;
        }

        const data = event.data;

        if (! data || data.type !== 'lms-scorm-course-complete') {
            return;
        }

        window.dispatchEvent(new CustomEvent('scorm-course-complete'));
    });
})();
</script>
