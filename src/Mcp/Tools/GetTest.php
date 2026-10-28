<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Test;

class GetTest extends LmsTool
{
    protected string $name = 'get_test';

    protected string $description = 'Get a quiz (test) with its questions, options, and correct answers from the rubric. Quiz edits go through the Filament admin.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Test ID (the step material_id, or test.id in step responses).')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $test = Test::query()->findOrFail($validated['id']);

        return Response::structured($this->serializeTest($test));
    }
}
