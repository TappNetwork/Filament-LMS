<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;

class UpdateStep extends LmsTool
{
    protected string $name = 'update_step';

    protected string $description = 'Update a step name, slug, lesson, optional flag, transcript text, or video name/url.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Step ID.')->required(),
            'name' => $schema->string()->description('Step name.'),
            'slug' => $schema->string()->description('URL slug.'),
            'lesson_id' => $schema->integer()->description('Lesson ID.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped.'),
            'text' => $schema->string()->description('Optional transcript or supporting text.'),
            'video_url' => $schema->string()->description('YouTube or Vimeo URL.'),
            'video_name' => $schema->string()->description('Video record name.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lms_steps,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'lesson_id' => ['sometimes', 'integer', 'exists:lms_lessons,id'],
            'is_optional' => ['sometimes', 'boolean'],
            'text' => ['sometimes', 'nullable', 'string'],
            'video_url' => ['sometimes', 'nullable', 'string'],
            'video_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $step = Step::query()->with('material')->findOrFail($validated['id']);

        if (isset($validated['slug'])) {
            $this->assertUniqueStepSlug($validated['slug'], $step->id);
        }

        $url = isset($validated['video_url'])
            ? $this->resolveVideoUrl((string) $validated['video_url'])
            : null;

        if (isset($validated['video_url']) && $step->material_type !== null && $step->material_type !== 'video') {
            throw ValidationException::withMessages([
                'video_url' => 'This step is not a video. Create a new video step instead of changing the material type.',
            ]);
        }

        $step = DB::transaction(function () use ($step, $validated, $url): Step {
            $step->update(collect($validated)->only(['name', 'slug', 'lesson_id', 'is_optional', 'text'])->all());

            if (isset($validated['video_url']) || isset($validated['video_name'])) {
                $video = $step->material instanceof Video ? $step->material : null;

                if ($video instanceof Video) {
                    $video->update(array_filter([
                        'name' => $validated['video_name'] ?? null,
                        'url' => $url,
                    ], fn (mixed $value): bool => $value !== null));
                } elseif (isset($validated['video_url'])) {
                    $video = Video::create([
                        'name' => $validated['video_name'] ?? $step->name,
                        'url' => $url,
                    ]);
                    $step->update([
                        'material_id' => $video->id,
                        'material_type' => 'video',
                    ]);
                }
            }

            return $step->refresh()->load('material');
        });

        return Response::structured($this->serializeStep($step));
    }
}
