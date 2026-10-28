<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentLms\Mcp\Concerns\HasSanctumAbilities;
use Tapp\FilamentLms\Mcp\Tools\CreateDocumentStep;
use Tapp\FilamentLms\Mcp\Tools\CreateImageStep;
use Tapp\FilamentLms\Mcp\Tools\CreateLesson;
use Tapp\FilamentLms\Mcp\Tools\CreateLinkStep;
use Tapp\FilamentLms\Mcp\Tools\CreateTestStep;
use Tapp\FilamentLms\Mcp\Tools\CreateTextStep;
use Tapp\FilamentLms\Mcp\Tools\CreateVideoCourse;
use Tapp\FilamentLms\Mcp\Tools\CreateVideoStep;
use Tapp\FilamentLms\Mcp\Tools\DeleteCourse;
use Tapp\FilamentLms\Mcp\Tools\DeleteLesson;
use Tapp\FilamentLms\Mcp\Tools\DeleteStep;
use Tapp\FilamentLms\Mcp\Tools\GetCourse;
use Tapp\FilamentLms\Mcp\Tools\GetTest;
use Tapp\FilamentLms\Mcp\Tools\ListCourses;
use Tapp\FilamentLms\Mcp\Tools\UpdateCourse;
use Tapp\FilamentLms\Mcp\Tools\UpdateLesson;
use Tapp\FilamentLms\Mcp\Tools\UpdateStep;

class LmsServer extends Server
{
    use HasSanctumAbilities;

    protected string $name = 'Filament LMS';

    protected string $version = '1.1.0';

    public int $defaultPaginationLength = 50;

    protected string $instructions = <<<'MARKDOWN'
        Write LMS admin data: Course → Lesson → Step.

        Videos are hosted YouTube or Vimeo URLs (converted with VideoUrlService). Do not upload video files.
        Optional transcripts belong on the step `text` field.
        New courses default to `is_private = true`. There is no draft flag.

        Prefer `create_video_course` for a full nested create. Use the granular course, lesson, and step tools for edits afterward.
        Step types: `create_video_step`, `create_text_step` (Markdown only), `create_link_step`, `create_document_step` (PDF), `create_image_step`, and `create_test_step` (graded quiz).
        Documents and images are downloaded by the server from a public https URL. Files cannot be uploaded through MCP.
        `create_test_step` needs a correct answer for every question. Read a quiz back with `get_test`. Quiz edits go through the Filament admin.
        Delete tools remove data. Confirm with the user before calling them.

        Deferred in this version: quiz editing, credits, evaluations, standalone forms, SCORM, course images, and user assignment.
    MARKDOWN;

    /**
     * Host apps spread this into their own server's $tools.
     *
     * @var list<class-string<Tool>>
     */
    public const TOOLS = [
        CreateVideoCourse::class,
        ListCourses::class,
        GetCourse::class,
        UpdateCourse::class,
        DeleteCourse::class,
        CreateLesson::class,
        UpdateLesson::class,
        DeleteLesson::class,
        CreateVideoStep::class,
        CreateTextStep::class,
        CreateLinkStep::class,
        CreateDocumentStep::class,
        CreateImageStep::class,
        CreateTestStep::class,
        GetTest::class,
        UpdateStep::class,
        DeleteStep::class,
    ];

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = self::TOOLS;
}
