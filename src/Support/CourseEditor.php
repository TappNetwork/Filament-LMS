<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

final class CourseEditor
{
    public static function isBuilder(): bool
    {
        return config('filament-lms.course_editor.mode', 'legacy') === 'builder';
    }

    public static function showContentResources(): bool
    {
        return (bool) config('filament-lms.course_editor.show_content_resources', true);
    }
}
