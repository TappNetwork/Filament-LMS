<script>
(function () {
    window.lmsConfirmHtml5CourseComplete = function () {
        const canComplete = document.body?.dataset?.lmsCanComplete === '1';

        if (! canComplete) {
            window.alert(
                'Continue the course in the player before marking it complete. Progress is recorded when you move through content in the player.'
            );

            return false;
        }

        return window.confirm('Mark this course as complete before leaving?');
    };

    window.lmsPostHtml5CourseComplete = function (commitUrl, csrfToken) {
        return fetch(commitUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ lesson_status: 'completed', html5_complete: true }),
        }).then(function (response) {
            if (! response.ok) {
                return response.json().then(function (data) {
                    window.alert(data.message || 'Could not mark the course complete yet.');

                    throw new Error('completion rejected');
                });
            }

            return response.json();
        });
    };
})();
</script>
