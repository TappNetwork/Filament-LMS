<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Tapp\FilamentLms\Concerns\RemembersErrorBag;
use Tapp\FilamentLms\Helpers\TenantHelper;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\LessonResource;
use Tapp\FilamentLms\Resources\StepResource;

class CourseCurriculum extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use RemembersErrorBag;

    #[Locked]
    public Course $record;

    public function mount(Course $record): void
    {
        abort_unless(CourseResource::can('update', $record) || CourseResource::can('view', $record), 403);

        $this->record = $record;
    }

    public function render(): View
    {
        return view('filament-lms::filament.course-editor.outline', [
            'lessons' => $this->outlineLessons(),
            'canUpdateCourse' => CourseResource::can('update', $this->record),
        ]);
    }

    /**
     * @return Collection<int, Lesson>
     */
    public function outlineLessons(): Collection
    {
        return $this->record->lessons()
            ->with(['steps.material'])
            ->get();
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderLessons(array $orderedIds): void
    {
        $course = $this->course();
        abort_unless(CourseResource::can('update', $course), 403);

        $existing = $course->lessons()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $ordered = $this->normalizeOrder($orderedIds, $existing);

        foreach ($ordered as $lessonId) {
            $lesson = $this->findLesson($lessonId);
            abort_unless(LessonResource::can('update', $lesson) || LessonResource::can('reorder', $lesson), 403);
        }

        Lesson::setNewOrder($ordered, 1, null, fn ($query) => $query->where('course_id', $course->getKey()));

        $this->refreshOutline();
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderSteps(int $lessonId, array $orderedIds): void
    {
        $course = $this->course();
        abort_unless(CourseResource::can('update', $course), 403);

        $lesson = $this->findLesson($lessonId);
        abort_unless(LessonResource::can('update', $lesson), 403);

        $existing = $lesson->steps()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $ordered = $this->normalizeOrder($orderedIds, $existing);

        foreach ($ordered as $stepId) {
            $step = $this->findStep($stepId);
            abort_unless($step->lesson_id === $lesson->id, 403);
            abort_unless(StepResource::can('update', $step) || StepResource::can('reorder', $step), 403);
        }

        Step::setNewOrder($ordered, 1, null, fn ($query) => $query->where('lesson_id', $lesson->getKey()));

        $this->refreshOutline();
    }

    public function duplicateStep(int $stepId): void
    {
        abort_unless(StepResource::can('create'), 403);

        $step = $this->findStep($stepId);
        abort_unless(StepResource::can('view', $step), 403);
        abort_unless(LessonResource::can('update', $step->lesson), 403);

        $copy = $step->replicate();
        $copy->name = $this->copiedName($step->name);
        $copy->slug = $this->uniqueStepSlug($step->lesson->slug.'-'.Str::slug($copy->name));
        $copy->lesson_id = $step->lesson_id;
        $this->assignTenant($copy, $step->lesson);
        $copy->save();

        Notification::make()->title(__('filament-lms::course-editor.outline.step_duplicated'))->success()->send();
        $this->refreshOutline();
    }

    public function createLessonAction(): Action
    {
        return Action::make('createLesson')
            ->modalHeading(__('filament-lms::course-editor.outline.add_lesson'))
            ->modalSubmitActionLabel(__('filament-lms::course-editor.outline.add_lesson'))
            ->authorize(fn (): bool => LessonResource::can('create') && CourseResource::can('update', $this->course()))
            ->schema($this->lessonFormSchema())
            ->action(function (array $data): void {
                $course = $this->course();
                abort_unless(LessonResource::can('create') && CourseResource::can('update', $course), 403);

                $lesson = new Lesson([
                    'course_id' => $course->getKey(),
                    'name' => $data['name'],
                    'slug' => $this->uniqueLessonSlug($course->getKey(), (string) $data['slug']),
                ]);
                $this->assignTenant($lesson, $course);
                $lesson->save();

                Notification::make()->title(__('filament-lms::course-editor.outline.lesson_added'))->success()->send();
                $this->refreshOutline();
            });
    }

    public function editLessonAction(): Action
    {
        return Action::make('editLesson')
            ->modalHeading(__('filament-lms::course-editor.outline.edit_lesson'))
            ->modalSubmitActionLabel(__('filament-lms::course-editor.outline.save_lesson'))
            ->fillForm(function (array $arguments): array {
                $lesson = $this->findLesson((int) $arguments['lesson']);

                return [
                    'name' => $lesson->name,
                    'slug' => $lesson->slug,
                    'slug_is_locked' => $lesson->slug !== Str::slug($lesson->name),
                ];
            })
            ->schema($this->lessonFormSchema())
            ->authorize(fn (array $arguments): bool => LessonResource::can('update', $this->findLesson((int) $arguments['lesson'])))
            ->action(function (array $data, array $arguments): void {
                $lesson = $this->findLesson((int) $arguments['lesson']);
                abort_unless(LessonResource::can('update', $lesson), 403);

                $lesson->update([
                    'name' => $data['name'],
                    'slug' => $this->uniqueLessonSlug($lesson->course_id, (string) $data['slug'], $lesson->getKey()),
                ]);

                Notification::make()->title(__('filament-lms::course-editor.outline.lesson_saved'))->success()->send();
                $this->refreshOutline();
            });
    }

    public function cloneLessonAction(): Action
    {
        return Action::make('cloneLesson')
            ->label(__('filament-lms::course-editor.outline.clone_lesson'))
            ->requiresConfirmation()
            ->modalHeading(__('filament-lms::course-editor.outline.clone_lesson'))
            ->modalDescription(__('filament-lms::course-editor.outline.clone_description'))
            ->modalSubmitActionLabel(__('filament-lms::course-editor.outline.clone'))
            ->authorize(fn (array $arguments): bool => LessonResource::can('view', $this->findLesson((int) $arguments['lesson']))
                && LessonResource::can('create')
                && CourseResource::can('update', $this->course()))
            ->action(function (array $arguments): void {
                $lesson = $this->findLesson((int) $arguments['lesson']);
                abort_unless(LessonResource::can('create') && CourseResource::can('update', $this->course()), 403);

                DB::transaction(function () use ($lesson): void {
                    $lesson->load('steps');

                    $copy = $lesson->replicate();
                    $copy->name = $this->copiedName($lesson->name);
                    $copy->slug = $this->uniqueLessonSlug($lesson->course_id, Str::slug($copy->name));
                    $copy->course_id = $lesson->course_id;
                    $this->assignTenant($copy, $lesson->course);
                    $copy->save();

                    $stepMap = [];

                    foreach ($lesson->steps as $step) {
                        $stepCopy = $step->replicate();
                        $stepCopy->lesson_id = $copy->getKey();
                        $stepCopy->retry_step_id = null;
                        $stepCopy->slug = $this->uniqueStepSlug($copy->slug.'-'.Str::slug($step->name));
                        $this->assignTenant($stepCopy, $copy);
                        $stepCopy->save();
                        $stepMap[$step->getKey()] = $stepCopy->getKey();
                    }

                    foreach ($lesson->steps as $step) {
                        if ($step->retry_step_id === null) {
                            continue;
                        }

                        $newStepId = $stepMap[$step->getKey()] ?? null;
                        $newRetryId = $stepMap[$step->retry_step_id] ?? $step->retry_step_id;

                        if ($newStepId === null) {
                            continue;
                        }

                        Step::query()->whereKey($newStepId)->update([
                            'retry_step_id' => $newRetryId,
                        ]);
                    }
                });

                Notification::make()->title(__('filament-lms::course-editor.outline.lesson_cloned'))->success()->send();
                $this->refreshOutline();
            });
    }

    public function deleteLessonAction(): Action
    {
        return Action::make('deleteLesson')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('filament-lms::course-editor.outline.delete_lesson'))
            ->modalDescription(__('filament-lms::course-editor.outline.delete_lesson_description'))
            ->modalSubmitActionLabel(__('filament-lms::course-editor.outline.delete'))
            ->authorize(fn (array $arguments): bool => LessonResource::can('delete', $this->findLesson((int) $arguments['lesson'])))
            ->action(function (array $arguments): void {
                $lesson = $this->findLesson((int) $arguments['lesson']);
                abort_unless(LessonResource::can('delete', $lesson), 403);
                $lesson->delete();

                Notification::make()->title(__('filament-lms::course-editor.outline.lesson_deleted'))->success()->send();
                $this->refreshOutline();
            });
    }

    public function deleteStepAction(): Action
    {
        return Action::make('deleteStep')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('filament-lms::course-editor.outline.delete_step'))
            ->modalSubmitActionLabel(__('filament-lms::course-editor.outline.delete'))
            ->authorize(fn (array $arguments): bool => StepResource::can('delete', $this->findStep((int) $arguments['step'])))
            ->action(function (array $arguments): void {
                $step = $this->findStep((int) $arguments['step']);
                abort_unless(StepResource::can('delete', $step), 403);
                $step->delete();

                Notification::make()->title(__('filament-lms::course-editor.outline.step_deleted'))->success()->send();
                $this->refreshOutline();
            });
    }

    public function editStepAction(): Action
    {
        return Action::make('editStep')
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(__('filament-lms::course-editor.step.save'))
            ->modalCancelActionLabel(__('filament-lms::course-editor.actions.cancel'))
            ->modalHeading(function (array $arguments): string {
                if (! isset($arguments['step'])) {
                    return __('filament-lms::course-editor.step.new_heading');
                }

                return __('filament-lms::course-editor.step.edit_heading', [
                    'name' => $this->findStep((int) $arguments['step'])->name,
                ]);
            })
            ->model(Step::class)
            ->record(function (array $arguments): ?Step {
                if (! isset($arguments['step'])) {
                    return null;
                }

                return $this->findStep((int) $arguments['step']);
            })
            ->authorize(function (array $arguments): bool {
                if (! isset($arguments['step'])) {
                    return StepResource::can('create')
                        && LessonResource::can('update', $this->findLesson((int) $arguments['lesson']));
                }

                return StepResource::can('update', $this->findStep((int) $arguments['step']));
            })
            ->fillForm(function (array $arguments): array {
                if (! isset($arguments['step'])) {
                    $lesson = $this->findLesson((int) $arguments['lesson']);
                    $name = __('filament-lms::course-editor.step.default_name', [
                        'number' => $lesson->steps()->count() + 1,
                    ]);

                    return [
                        'lesson_id' => $lesson->getKey(),
                        'name' => $name,
                        'slug' => $this->uniqueStepSlug($lesson->slug.'-'.Str::slug($name)),
                        'slug_is_locked' => false,
                        'is_optional' => false,
                        'require_perfect_score' => false,
                        'text' => null,
                        'material_type' => null,
                        'material_id' => null,
                    ];
                }

                $step = $this->findStep((int) $arguments['step']);

                return [
                    'id' => $step->getKey(),
                    'lesson_id' => $step->lesson_id,
                    'name' => $step->name,
                    'slug' => $step->slug,
                    'slug_is_locked' => true,
                    'text' => $step->text,
                    'is_optional' => $step->is_optional,
                    'material_type' => $step->material_type,
                    'material_id' => $step->material_id,
                    'require_perfect_score' => $step->require_perfect_score,
                    'retry_step_id' => $step->retry_step_id,
                    'player_slide_id' => $step->player_slide_id,
                ];
            })
            ->schema(fn (): array => StepResource::getFormComponents(inCourseEditor: true))
            ->action(function (array $data, array $arguments): void {
                $this->saveStep($data, $arguments);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    protected function saveStep(array $data, array $arguments): void
    {
        $lesson = $this->findLesson((int) $data['lesson_id']);
        $materialType = filled($data['material_type'] ?? null) ? (string) $data['material_type'] : null;
        $materialId = $materialType && filled($data['material_id'] ?? null) ? (int) $data['material_id'] : null;
        $isTest = $materialType === 'test';

        $retryStepId = $isTest && filled($data['retry_step_id'] ?? null) ? (int) $data['retry_step_id'] : null;
        $exceptId = isset($arguments['step']) ? (int) $arguments['step'] : null;

        if ($retryStepId !== null && ! in_array($retryStepId, StepResource::retryStepIdsForLesson($lesson->getKey(), $exceptId), true)) {
            throw ValidationException::withMessages([
                'retry_step_id' => __('filament-lms::course-editor.step.validation.retry_step'),
            ]);
        }

        $payload = [
            'lesson_id' => $lesson->getKey(),
            'name' => $data['name'],
            'slug' => $this->uniqueStepSlug((string) $data['slug'], $exceptId),
            'text' => filled($data['text'] ?? null) ? $data['text'] : null,
            'is_optional' => (bool) ($data['is_optional'] ?? false),
            'material_type' => $materialType,
            'material_id' => $materialId,
            'require_perfect_score' => $isTest && (bool) ($data['require_perfect_score'] ?? false),
            'retry_step_id' => $retryStepId,
        ];

        if (array_key_exists('player_slide_id', $data)) {
            $payload['player_slide_id'] = filled($data['player_slide_id']) ? $data['player_slide_id'] : null;
        }

        if (isset($arguments['step'])) {
            $step = $this->findStep((int) $arguments['step']);
            abort_unless(StepResource::can('update', $step), 403);
            $step->update($payload);
        } else {
            abort_unless(StepResource::can('create'), 403);
            $step = new Step($payload);
            $this->assignTenant($step, $lesson);
            $step->save();
        }

        Notification::make()->title(__('filament-lms::course-editor.step.saved'))->success()->send();
        $this->refreshOutline();
    }

    /**
     * @return array<int, Hidden|TextInput>
     */
    protected function lessonFormSchema(): array
    {
        return [
            Hidden::make('slug_is_locked')->dehydrated(false),
            TextInput::make('name')
                ->label(__('filament-lms::course-editor.fields.name.label'))
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    if ($get('slug_is_locked')) {
                        return;
                    }

                    $set('slug', Str::slug($state ?? ''));
                }),
            TextInput::make('slug')
                ->label(__('filament-lms::course-editor.fields.slug.label'))
                ->required()
                ->maxLength(255)
                ->helperText(__('filament-lms::course-editor.fields.slug.helper'))
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    $set('slug_is_locked', Str::slug((string) $get('name')) !== (string) $state);
                }),
        ];
    }

    protected function course(): Course
    {
        return $this->record;
    }

    protected function findLesson(int $lessonId): Lesson
    {
        return $this->course()->lessons()->whereKey($lessonId)->firstOrFail();
    }

    protected function findStep(int $stepId): Step
    {
        return Step::query()
            ->whereKey($stepId)
            ->whereHas('lesson', fn ($query) => $query->where('course_id', $this->course()->getKey()))
            ->firstOrFail();
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     * @param  array<int, int>  $existingIds
     * @return array<int, int>
     */
    protected function normalizeOrder(array $orderedIds, array $existingIds): array
    {
        $ordered = [];

        foreach ($orderedIds as $id) {
            $id = (int) $id;

            if (in_array($id, $existingIds, true) && ! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        foreach ($existingIds as $id) {
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }

    protected function assignTenant(Model $model, ?Model $parent = null): void
    {
        if (! config('filament-lms.tenancy.enabled')) {
            return;
        }

        $column = TenantHelper::getTenantColumnName();

        if (! empty($model->{$column})) {
            return;
        }

        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            $model->{$column} = $tenant->getKey();

            return;
        }

        if ($parent !== null && ! empty($parent->{$column})) {
            $model->{$column} = $parent->{$column};
        }
    }

    protected function uniqueLessonSlug(int $courseId, string $slug, ?int $ignoreId = null): string
    {
        $base = $slug !== '' ? $slug : 'lesson';
        $candidate = $base;
        $suffix = 2;

        while (
            Lesson::query()
                ->where('course_id', $courseId)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function uniqueStepSlug(string $slug, ?int $ignoreId = null): string
    {
        return StepResource::uniqueStepSlug($slug, $ignoreId);
    }

    protected function copiedName(string $name): string
    {
        $copy = $name.' '.__('filament-lms::course-editor.outline.copy_suffix');

        return mb_substr($copy, 0, 255);
    }

    protected function refreshOutline(): void
    {
        $this->record->unsetRelation('lessons');
        $this->record->refresh();
    }
}
