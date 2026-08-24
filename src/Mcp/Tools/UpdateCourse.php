<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Course;

class UpdateCourse extends LmsTool
{
    protected string $name = 'update_course';

    protected string $description = 'Update course fields: name, description, slug, external_id, is_private, award, required_test_percentage, embedded_player, completion_mode.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Course ID.')->required(),
            'name' => $schema->string()->description('Course name.'),
            'description' => $schema->string()->description('Course description.'),
            'slug' => $schema->string()->description('URL slug.'),
            'external_id' => $schema->string()->description('Integration ID.'),
            'is_private' => $schema->boolean()->description('Private course flag.'),
            'award' => $schema->string()->description('Certificate award key.'),
            'required_test_percentage' => $schema->integer()->description('Required average test score (0-100).'),
            'embedded_player' => $schema->boolean()->description('Embedded player mode.'),
            'completion_mode' => $schema->string()->description('native, scorm12, or html5.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $id = is_numeric($request->get('id')) ? (int) $request->get('id') : null;

        $validated = $request->validate(array_merge([
            'id' => ['required', 'integer', 'exists:lms_courses,id'],
        ], $this->courseRules(ignoreId: $id)), $this->courseMessages());

        $course = Course::query()->findOrFail($validated['id']);
        $course->update($this->courseAttributesFromInput($validated));

        return Response::structured($this->serializeCourse($course->refresh()));
    }
}
