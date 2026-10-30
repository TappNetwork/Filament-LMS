<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Lesson;

class CreateTextStep extends LmsTool
{
    protected string $name = 'create_text_step';

    protected string $description = 'Create a text-only step on a lesson. The text is Markdown and is shown with a Next button; there is no material.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Step name.')->required(),
            'text' => $schema->string()->description('Step content in Markdown.')->required(),
            'slug' => $schema->string()->description('URL slug. Defaults to {course-slug}-{lesson-slug}-{step-slug}.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped. Defaults to false.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            ...$this->stepRules(),
            'text' => ['required', 'string'],
        ]);

        $lesson = Lesson::query()->find($validated['lesson_id']);

        if (! $lesson instanceof Lesson) {
            throw ValidationException::withMessages([
                'lesson_id' => 'The selected lesson id is invalid.',
            ]);
        }
        $step = $this->createStep($lesson, $validated, $this->newStepSlug($lesson, $validated), null);

        $this->auditWrite($request, ['step_id' => $step->id]);

        return Response::structured($this->serializeStep($step));
    }
}
