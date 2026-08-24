<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Course;

class GetCourse extends LmsTool
{
    protected string $name = 'get_course';

    protected string $description = 'Get one LMS course including nested lessons and steps.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Course ID.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lms_courses,id'],
        ]);

        $course = Course::query()->with(['lessons.steps.material'])->findOrFail($validated['id']);

        return Response::structured($this->serializeCourse($course));
    }
}
