<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;

class CreateLesson extends LmsTool
{
    protected string $name = 'create_lesson';

    protected string $description = 'Create a lesson on an existing course.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'course_id' => $schema->integer()->description('Course ID.')->required(),
            'name' => $schema->string()->description('Lesson name.')->required(),
            'slug' => $schema->string()->description('URL slug. Defaults to a slugified name.'),
            'order' => $schema->integer()->description('Lesson order. Defaults to the next order in the course.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:lms_courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $course = Course::query()->findOrFail($validated['course_id']);
        $name = trim((string) $validated['name']);

        $lesson = Lesson::create([
            'course_id' => $course->id,
            'name' => $name,
            'slug' => filled($validated['slug'] ?? null) ? (string) $validated['slug'] : Str::slug($name),
            'order' => $validated['order'] ?? $this->nextLessonOrder($course),
        ]);

        return Response::structured($this->serializeLesson($lesson));
    }
}
