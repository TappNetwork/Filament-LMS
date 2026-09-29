<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Course;

class ListCourses extends LmsTool
{
    protected string $name = 'list_courses';

    protected string $description = 'List LMS courses including nested lessons and steps.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional search against name, slug, or external_id.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $courses = Course::query()
            ->with(['lessons.steps.material'])
            ->when(filled($validated['query'] ?? null), function ($query) use ($validated): void {
                $term = '%'.$validated['query'].'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('external_id', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get();

        return Response::structured([
            'courses' => $courses->map(fn (Course $course): array => $this->serializeCourse($course))->all(),
        ]);
    }
}
