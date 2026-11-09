<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Mcp\RemoteMediaFetcher;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;

class CreateDocumentStep extends LmsTool
{
    protected string $name = 'create_document_step';

    protected string $description = 'Create a PDF document step. The server downloads the PDF from a public https URL (no redirects, size-limited, must be application/pdf).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Step name.')->required(),
            'file_url' => $schema->string()->description('Public https URL of a PDF. Redirects are not followed.')->required(),
            'text' => $schema->string()->description('Optional instructions shown above the document, in Markdown.'),
            'slug' => $schema->string()->description('URL slug. Defaults to {course-slug}-{lesson-slug}-{step-slug}.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped. Defaults to false.'),
            'document_name' => $schema->string()->description('Document record name. Defaults to the step name.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            ...$this->stepRules(),
            'file_url' => ['required', 'string', 'max:2048', 'url:https'],
            'document_name' => ['nullable', 'string', 'max:255'],
        ]);

        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);
        $step = $this->createMediaStep(
            $lesson,
            $validated,
            Document::class,
            'default',
            RemoteMediaFetcher::DOCUMENT_MIME_TYPES,
            filled($validated['document_name'] ?? null) ? (string) $validated['document_name'] : trim((string) $validated['name']),
        );

        $this->auditWrite($request, ['step_id' => $step->id, 'document_id' => $step->material_id]);

        return Response::structured($this->serializeStep($step));
    }
}
