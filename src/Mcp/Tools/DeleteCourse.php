<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Course;

#[IsDestructive]
class DeleteCourse extends LmsTool
{
    protected string $name = 'delete_course';

    protected string $description = 'Delete a course and its lessons, steps, and materials that no other step uses.';

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

        $course = Course::query()->with('lessons.steps')->findOrFail($validated['id']);

        DB::transaction(function () use ($course): void {
            foreach ($course->lessons as $lesson) {
                foreach ($lesson->steps as $step) {
                    $this->deleteStepMaterial($step);
                    $step->delete();
                }

                $lesson->delete();
            }

            $course->delete();
        });

        $this->auditWrite($request, ['course_id' => $validated['id']]);

        return Response::structured([
            'deleted' => true,
            'id' => $validated['id'],
        ]);
    }
}
