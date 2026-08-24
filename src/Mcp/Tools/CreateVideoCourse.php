<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;

class CreateVideoCourse extends LmsTool
{
    protected string $name = 'create_video_course';

    protected string $description = 'Create a private video course with nested lessons and YouTube/Vimeo steps in one call.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Course name. Also used to auto-generate slug and external_id.')->required(),
            'description' => $schema->string()->description('Course description.'),
            'slug' => $schema->string()->description('URL slug. Defaults to a slugified name.'),
            'external_id' => $schema->string()->description('Integration ID. Lowercase letters, numbers, underscores; must start with a letter. Defaults to a slugified name with underscores.'),
            'award' => $schema->string()->description('Certificate award key. Defaults to default.'),
            'is_private' => $schema->boolean()->description('Private courses are only visible to assigned users and LMS admins. Defaults to true.'),
            'required_test_percentage' => $schema->integer()->description('Required average test score (0-100). Defaults to 0.'),
            'embedded_player' => $schema->boolean()->description('Embedded player mode. Defaults to false.'),
            'completion_mode' => $schema->string()->description('native, scorm12, or html5. Defaults to native.'),
            'lessons' => $schema->array()->description('Lessons, each with name and steps[{name, video_url, text?, is_optional?}].')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $this->applyGeneratedCourseFields($request);

        $validated = $request->validate(array_merge($this->courseRules(creating: true), [
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.name' => ['required', 'string', 'max:255'],
            'lessons.*.slug' => ['nullable', 'string', 'max:255'],
            'lessons.*.steps' => ['required', 'array', 'min:1'],
            'lessons.*.steps.*.name' => ['required', 'string', 'max:255'],
            'lessons.*.steps.*.video_url' => ['required', 'string'],
            'lessons.*.steps.*.slug' => ['nullable', 'string', 'max:255'],
            'lessons.*.steps.*.text' => ['nullable', 'string'],
            'lessons.*.steps.*.is_optional' => ['sometimes', 'boolean'],
            'lessons.*.steps.*.video_name' => ['nullable', 'string', 'max:255'],
        ]), $this->courseMessages());

        $course = DB::transaction(function () use ($validated): Course {
            $course = Course::create($this->courseAttributesFromInput($validated, creating: true));

            foreach (array_values($validated['lessons']) as $index => $lessonInput) {
                $lessonName = trim((string) $lessonInput['name']);
                $lesson = Lesson::create([
                    'course_id' => $course->id,
                    'name' => $lessonName,
                    'slug' => filled($lessonInput['slug'] ?? null) ? (string) $lessonInput['slug'] : Str::slug($lessonName),
                    'order' => $index + 1,
                ]);

                foreach (array_values($lessonInput['steps']) as $stepIndex => $stepInput) {
                    $this->createVideoStep($lesson, $stepInput, $stepIndex + 1);
                }
            }

            return $course->refresh();
        });

        return Response::structured($this->serializeCourse($course));
    }
}
