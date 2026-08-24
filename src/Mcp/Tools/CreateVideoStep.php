<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Lesson;

class CreateVideoStep extends LmsTool
{
    protected string $name = 'create_video_step';

    protected string $description = 'Create a video step on a lesson from a YouTube or Vimeo URL. Optional text is the transcript.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Step name.')->required(),
            'video_url' => $schema->string()->description('YouTube or Vimeo URL. Converted to an embed URL.')->required(),
            'slug' => $schema->string()->description('URL slug. Defaults to {lesson-slug}-{step-slug}.'),
            'text' => $schema->string()->description('Optional transcript or supporting text.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped. Defaults to false.'),
            'video_name' => $schema->string()->description('Video record name. Defaults to the step name.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lms_lessons,id'],
            'name' => ['required', 'string', 'max:255'],
            'video_url' => ['required', 'string'],
            'slug' => ['nullable', 'string', 'max:255'],
            'text' => ['nullable', 'string'],
            'is_optional' => ['sometimes', 'boolean'],
            'video_name' => ['nullable', 'string', 'max:255'],
        ]);

        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);
        $step = $this->createVideoStep($lesson, $validated);

        return Response::structured($this->serializeStep($step));
    }
}
