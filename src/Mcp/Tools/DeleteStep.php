<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Step;

class DeleteStep extends LmsTool
{
    protected string $name = 'delete_step';

    protected string $description = 'Delete a step and its video material when present.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Step ID.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lms_steps,id'],
        ]);

        $step = Step::query()->findOrFail($validated['id']);

        DB::transaction(function () use ($step): void {
            $this->deleteStepMaterial($step);
            $step->delete();
        });

        return Response::structured([
            'deleted' => true,
            'id' => $validated['id'],
        ]);
    }
}
