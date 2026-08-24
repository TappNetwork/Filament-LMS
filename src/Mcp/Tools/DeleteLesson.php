<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Lesson;

class DeleteLesson extends LmsTool
{
    protected string $name = 'delete_lesson';

    protected string $description = 'Delete a lesson and its steps and video materials.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Lesson ID.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lms_lessons,id'],
        ]);

        $lesson = Lesson::query()->with('steps')->findOrFail($validated['id']);

        DB::transaction(function () use ($lesson): void {
            foreach ($lesson->steps as $step) {
                $this->deleteStepMaterial($step);
            }

            $lesson->delete();
        });

        return Response::structured([
            'deleted' => true,
            'id' => $validated['id'],
        ]);
    }
}
