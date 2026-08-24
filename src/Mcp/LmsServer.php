<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp;

use Laravel\Mcp\Server;
use Tapp\FilamentLms\Mcp\Tools\CreateLesson;
use Tapp\FilamentLms\Mcp\Tools\CreateVideoCourse;
use Tapp\FilamentLms\Mcp\Tools\CreateVideoStep;
use Tapp\FilamentLms\Mcp\Tools\DeleteCourse;
use Tapp\FilamentLms\Mcp\Tools\DeleteLesson;
use Tapp\FilamentLms\Mcp\Tools\DeleteStep;
use Tapp\FilamentLms\Mcp\Tools\GetCourse;
use Tapp\FilamentLms\Mcp\Tools\ListCourses;
use Tapp\FilamentLms\Mcp\Tools\UpdateCourse;
use Tapp\FilamentLms\Mcp\Tools\UpdateLesson;
use Tapp\FilamentLms\Mcp\Tools\UpdateStep;

class LmsServer extends Server
{
    protected string $name = 'Filament LMS';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Write LMS admin data: Course → Lesson → Step.

        Videos are hosted YouTube or Vimeo URLs (converted with VideoUrlService). Do not upload video files.
        Optional transcripts belong on the step `text` field.
        New courses default to `is_private = true`. There is no draft flag.

        Prefer `create_video_course` for a full nested create. Use the granular course, lesson, and step tools for edits afterward.

        Deferred in this version: credits, evaluations, tests/forms, documents, SCORM, course images, and user assignment.
    MARKDOWN;

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        CreateVideoCourse::class,
        ListCourses::class,
        GetCourse::class,
        UpdateCourse::class,
        DeleteCourse::class,
        CreateLesson::class,
        UpdateLesson::class,
        DeleteLesson::class,
        CreateVideoStep::class,
        UpdateStep::class,
        DeleteStep::class,
    ];
}
