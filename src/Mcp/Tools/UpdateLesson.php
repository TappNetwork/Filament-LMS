<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Lesson;

class UpdateLesson extends LmsTool
{
    protected string $name = 'update_lesson';

    protected string $description = 'Update a lesson name, slug, course, or order.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Lesson name.'),
            'slug' => $schema->string()->description('URL slug.'),
            'course_id' => $schema->integer()->description('Course ID.'),
            'order' => $schema->integer()->description('Lesson order.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lms_lessons,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'course_id' => ['sometimes', 'integer', 'exists:lms_courses,id'],
            'order' => ['sometimes', 'integer', 'min:1'],
        ]);

        $lesson = Lesson::query()->findOrFail($validated['id']);
        $lesson->update(collect($validated)->except('id')->all());

        return Response::structured($this->serializeLesson($lesson->refresh()));
    }
}
