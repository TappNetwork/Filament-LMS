<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Services\VideoUrlService;
use Throwable;

abstract class LmsTool extends Tool
{
    protected function authorizeWrite(Request $request): ?Response
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        if ($user instanceof FilamentUser) {
            try {
                $panel = Filament::getPanel('lms');

                if ($user->canAccessPanel($panel)) {
                    return null;
                }
            } catch (Throwable) {
                // Panel may be unregistered in some hosts; fall through to isLmsAdmin().
            }
        }

        if (method_exists($user, 'isLmsAdmin') && $user->isLmsAdmin()) {
            return null;
        }

        return Response::error('You must be able to access the LMS Filament panel to use this tool.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function courseRules(?int $ignoreId = null, bool $creating = false): array
    {
        $awardKeys = array_keys(config('filament-lms.awards', ['default' => 'Default']));
        $unique = fn (string $column): \Illuminate\Validation\Rules\Unique => $ignoreId === null
            ? Rule::unique('lms_courses', $column)
            : Rule::unique('lms_courses', $column)->ignore($ignoreId);

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255', $unique('name')],
            'description' => ['sometimes', 'nullable', 'string'],
            'slug' => [$creating ? 'required' : 'sometimes', 'string', 'max:255', $unique('slug')],
            'external_id' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                $unique('external_id'),
            ],
            'is_private' => ['sometimes', 'boolean'],
            'award' => ['sometimes', 'nullable', 'string', Rule::in($awardKeys)],
            'required_test_percentage' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'embedded_player' => ['sometimes', 'boolean'],
            'completion_mode' => ['sometimes', 'nullable', 'string', Rule::enum(CompletionMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function courseMessages(): array
    {
        return [
            'external_id.regex' => 'External ID must contain only lowercase letters, numbers, and underscores, and must start with a letter.',
            'external_id.max' => 'External ID cannot exceed 100 characters.',
            'name.unique' => 'A course with this name already exists.',
            'slug.unique' => 'A course with this slug already exists.',
            'external_id.unique' => 'A course with this external ID already exists.',
        ];
    }

    protected function applyGeneratedCourseFields(Request $request): void
    {
        $name = trim((string) $request->get('name', ''));

        if ($name === '') {
            return;
        }

        if (blank($request->get('slug'))) {
            $request->merge(['slug' => Str::slug($name)]);
        }

        if (blank($request->get('external_id'))) {
            $request->merge(['external_id' => Str::slug($name, '_')]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function courseAttributesFromInput(array $input, bool $creating = false): array
    {
        $name = isset($input['name']) ? trim((string) $input['name']) : null;
        $attributes = [];

        if ($name !== null) {
            $attributes['name'] = $name;
        }

        if (array_key_exists('description', $input)) {
            $attributes['description'] = $input['description'];
        }

        if ($creating) {
            $attributes['slug'] = filled($input['slug'] ?? null) ? (string) $input['slug'] : Str::slug((string) $name);
            $attributes['external_id'] = filled($input['external_id'] ?? null)
                ? (string) $input['external_id']
                : Str::slug((string) $name, '_');
            $attributes['award'] = $input['award'] ?? 'default';
            $attributes['completion_mode'] = $input['completion_mode'] ?? CompletionMode::Native->value;
            $attributes['is_private'] = array_key_exists('is_private', $input)
                ? (bool) $input['is_private']
                : true;
            $attributes['embedded_player'] = (bool) ($input['embedded_player'] ?? false);
            $attributes['required_test_percentage'] = $input['required_test_percentage'] ?? 0;
        } else {
            foreach (['slug', 'external_id', 'award', 'completion_mode', 'required_test_percentage'] as $field) {
                if (array_key_exists($field, $input)) {
                    $attributes[$field] = $input[$field];
                }
            }

            if (array_key_exists('is_private', $input)) {
                $attributes['is_private'] = (bool) $input['is_private'];
            }

            if (array_key_exists('embedded_player', $input)) {
                $attributes['embedded_player'] = (bool) $input['embedded_player'];
            }
        }

        return $attributes;
    }

    /**
     * @throws ValidationException
     */
    protected function resolveVideoUrl(string $url): string
    {
        $result = VideoUrlService::validateAndConvertWithErrors($url);

        if ($result['errors'] !== []) {
            throw ValidationException::withMessages([
                'video_url' => $result['errors']['url'] ?? 'The video URL is invalid.',
            ]);
        }

        return $result['url'];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function createVideoStep(Lesson $lesson, array $input, ?int $order = null): Step
    {
        $name = trim((string) $input['name']);
        $slug = filled($input['slug'] ?? null)
            ? (string) $input['slug']
            : $lesson->slug.'-'.Str::slug($name);
        $videoName = filled($input['video_name'] ?? null) ? (string) $input['video_name'] : $name;
        $url = $this->resolveVideoUrl((string) $input['video_url']);

        $this->assertUniqueStepSlug($slug);

        $video = Video::create([
            'name' => $videoName,
            'url' => $url,
        ]);

        return Step::create([
            'lesson_id' => $lesson->id,
            'order' => $order ?? ($lesson->steps()->count() + 1),
            'name' => $name,
            'slug' => $slug,
            'text' => $input['text'] ?? null,
            'is_optional' => (bool) ($input['is_optional'] ?? false),
            'material_id' => $video->id,
            'material_type' => 'video',
        ]);
    }

    /**
     * @throws ValidationException
     */
    protected function assertUniqueStepSlug(string $slug, ?int $ignoreId = null): void
    {
        $query = Step::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'A step with this slug already exists.',
            ]);
        }
    }

    protected function nextLessonOrder(Course $course): int
    {
        return (int) $course->lessons()->max('order') + 1;
    }

    protected function deleteStepMaterial(Step $step): void
    {
        if ($step->material_type === 'video' && $step->material_id) {
            Video::query()->whereKey($step->material_id)->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCourse(Course $course, bool $includeChildren = true): array
    {
        $course->loadMissing(['lessons.steps.material']);

        $payload = [
            'id' => $course->id,
            'name' => $course->name,
            'slug' => $course->slug,
            'external_id' => $course->external_id,
            'description' => $course->description,
            'is_private' => (bool) $course->is_private,
            'award' => $course->award,
            'required_test_percentage' => $course->required_test_percentage,
            'embedded_player' => (bool) $course->embedded_player,
            'completion_mode' => $course->completionMode()->value,
            'urls' => $this->courseUrls($course),
        ];

        if ($includeChildren) {
            $payload['lessons'] = $course->lessons->map(fn (Lesson $lesson): array => $this->serializeLesson($lesson))->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeLesson(Lesson $lesson): array
    {
        $lesson->loadMissing(['steps.material']);

        return [
            'id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'name' => $lesson->name,
            'slug' => $lesson->slug,
            'order' => $lesson->order,
            'steps' => $lesson->steps->map(fn (Step $step): array => $this->serializeStep($step))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeStep(Step $step): array
    {
        $step->loadMissing('material');

        $video = $step->material instanceof Video ? $step->material : null;

        return [
            'id' => $step->id,
            'lesson_id' => $step->lesson_id,
            'name' => $step->name,
            'slug' => $step->slug,
            'order' => $step->order,
            'is_optional' => (bool) $step->is_optional,
            'text' => $step->text,
            'material_type' => $step->material_type,
            'material_id' => $step->material_id,
            'video' => $video === null ? null : [
                'id' => $video->id,
                'name' => $video->name,
                'url' => $video->url,
                'provider' => $video->provider,
            ],
            'urls' => $this->stepUrls($step),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function courseUrls(Course $course): array
    {
        return [
            'admin' => $this->safeUrl(fn (): string => CourseResource::getUrl('edit', ['record' => $course])),
            'learner' => $this->safeUrl(function () use ($course): string {
                $firstStep = $course->firstStep();

                return $firstStep instanceof Step
                    ? StepPage::getUrlForStep($firstStep)
                    : Dashboard::getUrl();
            }),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function stepUrls(Step $step): array
    {
        return [
            'learner' => $this->safeUrl(fn (): string => StepPage::getUrlForStep($step)),
        ];
    }

    /**
     * @param  callable(): string  $callback
     */
    protected function safeUrl(callable $callback): ?string
    {
        try {
            return $callback();
        } catch (Throwable) {
            return null;
        }
    }
}
