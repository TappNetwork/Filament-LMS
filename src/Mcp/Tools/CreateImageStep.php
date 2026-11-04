<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Mcp\RemoteMediaFetcher;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Lesson;

class CreateImageStep extends LmsTool
{
    protected string $name = 'create_image_step';

    protected string $description = 'Create an image step. The server downloads a JPEG, PNG, GIF, or WebP from a public https URL (no redirects, size-limited, no SVG).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Step name.')->required(),
            'file_url' => $schema->string()->description('Public https URL of a JPEG, PNG, GIF, or WebP image. Redirects are not followed.')->required(),
            'text' => $schema->string()->description('Optional caption or instructions, in Markdown.'),
            'slug' => $schema->string()->description('URL slug. Defaults to {course-slug}-{lesson-slug}-{step-slug}.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped. Defaults to false.'),
            'image_name' => $schema->string()->description('Image record name. Defaults to the step name.'),
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
            'image_name' => ['nullable', 'string', 'max:255'],
        ]);

        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);
        $step = $this->createMediaStep(
            $lesson,
            $validated,
            Image::class,
            'image',
            RemoteMediaFetcher::IMAGE_MIME_TYPES,
            filled($validated['image_name'] ?? null) ? (string) $validated['image_name'] : trim((string) $validated['name']),
        );

        $this->auditWrite($request, ['step_id' => $step->id, 'image_id' => $step->material_id]);

        return Response::structured($this->serializeStep($step));
    }
}
