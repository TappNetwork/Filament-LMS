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
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Step;

class CreateLinkStep extends LmsTool
{
    protected string $name = 'create_link_step';

    protected string $description = 'Create a step that sends the learner to an external web page. The server queues a screenshot preview of the URL, so it must be a public http(s) address.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Step name.')->required(),
            'url' => $schema->string()->description('Public http or https URL.')->required(),
            'text' => $schema->string()->description('Optional instructions shown above the link, in Markdown.'),
            'slug' => $schema->string()->description('URL slug. Defaults to {course-slug}-{lesson-slug}-{step-slug}.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped without visiting the link. Defaults to false.'),
            'link_name' => $schema->string()->description('Link record name. Defaults to the step name.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            ...$this->stepRules(),
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'link_name' => ['nullable', 'string', 'max:255'],
        ]);

        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);
        $slug = $this->newStepSlug($lesson, $validated);
        $url = $this->assertPublicLinkUrl((string) $validated['url'], 'url');

        $step = DB::transaction(function () use ($lesson, $validated, $slug, $url): Step {
            $link = Link::create([
                'name' => filled($validated['link_name'] ?? null) ? (string) $validated['link_name'] : trim((string) $validated['name']),
                'url' => $url,
            ]);

            return $this->createStep($lesson, $validated, $slug, $link);
        });

        $this->auditWrite($request, ['step_id' => $step->id, 'link_id' => $step->material_id]);

        return Response::structured($this->serializeStep($step));
    }
}
