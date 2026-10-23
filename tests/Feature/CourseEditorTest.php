<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Livewire\CourseCurriculum;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CreditCategory;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Scopes\TenantScope;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\CourseResource\Pages\CreateCourse;
use Tapp\FilamentLms\Resources\CourseResource\Pages\EditCourse;
use Tapp\FilamentLms\Resources\CourseResource\Schemas\CourseEditorForm;
use Tapp\FilamentLms\Resources\CreditCategoryResource;
use Tapp\FilamentLms\Resources\DocumentResource;
use Tapp\FilamentLms\Resources\ImageResource;
use Tapp\FilamentLms\Resources\LessonResource;
use Tapp\FilamentLms\Resources\LinkResource;
use Tapp\FilamentLms\Resources\StepResource;
use Tapp\FilamentLms\Resources\TestResource;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Support\CourseMaterialForm;
use Tapp\FilamentLms\Tests\TestUser;

function courseEditorAdmin(): TestUser
{
    return TestUser::query()->create([
        'name' => 'Course Editor Admin',
        'email' => 'course-editor-'.uniqid().'@example.com',
        'password' => 'password',
    ]);
}

function useCourseEditorPanel(): void
{
    config(['filament-lms.integrations.certificate_builder.template_resource' => null]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs(courseEditorAdmin());
}

beforeEach(function (): void {
    if (Schema::hasTable('lms_credit_categories')) {
        return;
    }

    Schema::create('lms_credit_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('color', 20)->default('blue');
        $table->timestamps();
    });

    Schema::create('lms_course_credit_category', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('course_id')->constrained('lms_courses')->cascadeOnDelete();
        $table->foreignId('credit_category_id')->constrained('lms_credit_categories')->cascadeOnDelete();
        $table->decimal('credits', 5, 2);
        $table->timestamps();
    });
});

it('puts the course image in its own section under visibility', function (): void {
    $columns = CourseEditorForm::builderComponents()[1]->getDefaultChildComponents();
    $sideHeadings = array_map(
        fn (mixed $section): mixed => $section->getHeading(),
        $columns[1]->getDefaultChildComponents(),
    );
    $generalNames = array_map(
        fn (mixed $field): ?string => method_exists($field, 'getName') ? $field->getName() : null,
        $columns[0]->getDefaultChildComponents(),
    );

    expect($sideHeadings)->toBe(['Visibility', 'Image', 'Credits'])
        ->and($generalNames)->not->toContain('image')
        ->and(collect(CourseEditorForm::legacyComponents())->contains(
            fn (mixed $field): bool => method_exists($field, 'getName') && $field->getName() === 'image',
        ))->toBeTrue();
});

it('explains embedded player with an info icon', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create([
        'name' => 'Welcome course',
        'slug' => 'welcome-course',
        'external_id' => 'welcome_course',
    ]);

    $html = Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('fi-lms-label-hint')
        ->toContain('Hides LMS step sidebar')
        ->toContain('x-on:mousedown.stop.prevent')
        ->toContain('x-on:click.stop.prevent');
});

it('describes the course details slug as used for urls', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create([
        'name' => 'Welcome course',
        'slug' => 'welcome-course',
        'external_id' => 'welcome_course',
    ]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertSuccessful()
        ->assertSee('Used for urls.')
        ->assertDontSee('Auto-generated from name');
});

it('renders the builder edit page with details curriculum and access tabs', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create([
        'name' => 'Welcome course',
        'slug' => 'welcome-course',
        'external_id' => 'welcome_course',
    ]);
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Welcome',
        'slug' => 'welcome',
        'order' => 4,
    ]);
    Step::factory()->withoutMaterial()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Introduction',
        'order' => 9,
    ]);

    $component = Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertSuccessful()
        ->assertSee('Details')
        ->assertSee('Curriculum')
        ->assertSee('Access')
        ->assertSee('Course outline')
        ->assertSee('No material')
        ->assertSee('Save changes')
        ->assertSee('Save & preview')
        ->assertSee('Lesson 1 · Welcome · 1 step');

    expect($component->instance()->getBreadcrumbs())->toBe([
        CourseResource::getUrl('index') => 'Courses',
        'Welcome course',
    ])->and($component->instance()->learnerUrl())->toContain('welcome-course');
});

it('spaces lesson actions like steps and shows filament tooltips', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create([
        'name' => 'Welcome course',
        'slug' => 'welcome-course',
        'external_id' => 'welcome_course',
    ]);
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Welcome',
        'slug' => 'welcome',
        'order' => 1,
    ]);
    Step::factory()->withoutMaterial()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Introduction',
        'order' => 1,
    ]);

    $html = Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertSuccessful()
        ->html();

    expect($html)
        ->toContain('class="flex items-center gap-2"')
        ->toContain("content: 'Edit lesson'")
        ->toContain("content: 'Clone lesson'")
        ->toContain("content: 'Delete lesson'")
        ->toContain("content: 'Edit step'")
        ->toContain("content: 'Duplicate step'")
        ->toContain("content: 'Delete step'")
        ->toContain("content: 'Reorder lesson'")
        ->toContain("content: 'Reorder step'");
});

it('hides the curriculum outline on create until the course exists', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    Livewire::test(CreateCourse::class)
        ->assertSuccessful()
        ->assertSee('Save the course to add lessons')
        ->assertSee('Save the course to manage access')
        ->assertDontSee('Course outline');
});

it('saves course details from the builder form', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create([
        'name' => 'Old course',
        'slug' => 'old-course',
        'external_id' => 'old_course',
        'description' => 'Old description',
        'completion_mode' => CompletionMode::Native->value,
        'is_private' => false,
        'embedded_player' => false,
        'required_test_percentage' => 0,
    ]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm([
            'name' => 'Updated course',
            'slug' => 'updated-course',
            'slug_is_locked' => true,
            'external_id' => 'updated_course',
            'description' => 'Updated description',
            'required_test_percentage' => 80,
            'completion_mode' => CompletionMode::Native->value,
            'embedded_player' => true,
            'is_private' => true,
            'certificate_template_id' => $course->certificate_template_id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $course->refresh();

    expect($course->name)->toBe('Updated course')
        ->and($course->slug)->toBe('updated-course')
        ->and($course->external_id)->toBe('updated_course')
        ->and($course->description)->toBe('Updated description')
        ->and($course->required_test_percentage)->toBe(80)
        ->and($course->is_private)->toBeTrue()
        ->and($course->embedded_player)->toBeTrue()
        ->and($course->completion_mode)->toBe(CompletionMode::Native);
});

it('saves course credits from the builder form when credits are enabled', function (): void {
    config([
        'filament-lms.course_editor.mode' => 'builder',
        'filament-lms.credits_enabled' => true,
    ]);
    useCourseEditorPanel();

    $course = Course::factory()->create([
        'name' => 'Credit course',
        'slug' => 'credit-course',
        'external_id' => 'credit_course',
        'completion_mode' => CompletionMode::Native->value,
    ]);
    $category = CreditCategory::query()->create([
        'name' => 'CEU',
        'slug' => 'ceu',
        'color' => 'blue',
    ]);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->fillForm([
            'name' => $course->name,
            'slug' => $course->slug,
            'slug_is_locked' => true,
            'external_id' => $course->external_id,
            'description' => $course->description,
            'required_test_percentage' => 0,
            'completion_mode' => CompletionMode::Native->value,
            'embedded_player' => false,
            'is_private' => false,
            'certificate_template_id' => $course->certificate_template_id,
            'courseCreditCategories' => [
                [
                    'credit_category_id' => $category->getKey(),
                    'credits' => 1.5,
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($course->courseCreditCategories()->first())
        ->credit_category_id->toBe($category->getKey())
        ->credits->toEqual(1.5);
});

it('renders lessons and steps in order and badges a step with no material', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $later = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Later lesson',
    ]);
    $earlier = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Earlier lesson',
    ]);
    $later->update(['order' => 10]);
    $earlier->update(['order' => 2]);
    Step::factory()->withoutMaterial()->create([
        'lesson_id' => $earlier->id,
        'name' => 'Missing material',
    ]);

    $html = Livewire::test(CourseCurriculum::class, ['record' => $course])->html();

    expect(strpos($html, 'Earlier lesson'))->toBeLessThan(strpos($html, 'Later lesson'))
        ->and($html)->toContain('Lesson 1 · Earlier lesson')
        ->and($html)->toContain('No material')
        ->and($later->order)->toBe(10);
});

it('reorders lessons and steps within their parent', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $other = Course::factory()->create();
    $first = Lesson::factory()->create(['course_id' => $course->id, 'order' => 5]);
    $second = Lesson::factory()->create(['course_id' => $course->id, 'order' => 8]);
    $untouched = Lesson::factory()->create(['course_id' => $other->id, 'order' => 1]);
    $stepA = Step::factory()->withoutMaterial()->create(['lesson_id' => $first->id, 'order' => 4]);
    $stepB = Step::factory()->withoutMaterial()->create(['lesson_id' => $first->id, 'order' => 7]);
    $otherStep = Step::factory()->withoutMaterial()->create(['lesson_id' => $second->id, 'order' => 1]);

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->call('reorderLessons', [$second->id, $first->id])
        ->call('reorderSteps', $first->id, [$stepB->id, $stepA->id]);

    expect($second->refresh()->order)->toBe(1)
        ->and($first->refresh()->order)->toBe(2)
        ->and($untouched->refresh()->order)->toBe(1)
        ->and($stepB->refresh()->order)->toBe(1)
        ->and($stepA->refresh()->order)->toBe(2)
        ->and($otherStep->refresh()->order)->toBe(1);
});

it('adds clones and deletes lessons from the outline', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Welcome',
        'slug' => 'welcome',
        'order' => 1,
    ]);
    $step = Step::factory()->withoutMaterial()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Intro',
    ]);

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->callAction('createLesson', data: [
            'name' => 'Safety basics',
            'slug' => 'safety-basics',
        ])
        ->callAction('cloneLesson', arguments: ['lesson' => $lesson->getKey()])
        ->assertSee('Safety basics')
        ->assertSee('Welcome copy');

    $created = Lesson::query()->where('slug', 'safety-basics')->first();
    $clone = Lesson::query()->where('name', 'Welcome copy')->first();

    expect($created)->not->toBeNull()
        ->and($created->course_id)->toBe($course->id)
        ->and($created->order)->toBe(2)
        ->and($clone)->not->toBeNull()
        ->and($clone->steps)->toHaveCount(1)
        ->and($clone->steps->first()->material_id)->toBe($step->material_id)
        ->and($clone->order)->toBe(3);

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->callAction('deleteLesson', arguments: ['lesson' => $lesson->getKey()]);

    expect(Lesson::query()->whereKey($lesson->getKey())->exists())->toBeFalse();
});

it('adds edits duplicates and deletes a step from the slide-over', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'slug' => 'welcome',
    ]);
    $video = Video::factory()->create(['name' => 'Welcome video']);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Introduction',
        'slug' => 'welcome-introduction',
        'material_type' => 'video',
        'material_id' => $video->id,
        'text' => 'Hello',
    ]);

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->callAction('editStep', data: [
            'lesson_id' => $lesson->id,
            'name' => 'Handbook',
            'slug' => 'welcome-handbook',
            'is_optional' => true,
            'text' => 'Read this',
            'material_type' => null,
            'material_id' => null,
            'require_perfect_score' => false,
        ], arguments: [
            'lesson' => $lesson->getKey(),
        ])
        ->call('duplicateStep', $step->getKey())
        ->callAction('editStep', data: [
            'id' => $step->id,
            'lesson_id' => $lesson->id,
            'name' => 'Introduction revised',
            'slug' => $step->slug,
            'slug_is_locked' => true,
            'is_optional' => false,
            'text' => 'Updated hello',
            'material_type' => null,
            'material_id' => null,
            'require_perfect_score' => false,
        ], arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $step->getKey(),
        ]);

    $created = Step::query()->where('slug', 'welcome-handbook')->first();
    $step->refresh();

    expect($created)->not->toBeNull()
        ->and($created->is_optional)->toBeTrue()
        ->and($created->material_type)->toBeNull()
        ->and($created->order)->toBe(2)
        ->and($step->name)->toBe('Introduction revised')
        ->and($step->text)->toBe('Updated hello')
        ->and($step->material_type)->toBeNull()
        ->and($step->material_id)->toBeNull()
        ->and(Step::query()->where('name', 'Introduction copy')->exists())->toBeTrue();

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->callAction('deleteStep', arguments: ['step' => $created->getKey()]);

    expect(Step::query()->whereKey($created->getKey())->exists())->toBeFalse();
});

it('gives a new step a unique slug when another lesson already uses that slug', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $defaultName = __('filament-lms::course-editor.step.default_name', ['number' => 1]);
    $baseSlug = 'welcome-'.Str::slug($defaultName);

    $otherCourse = Course::factory()->create();
    $otherLesson = Lesson::factory()->create([
        'course_id' => $otherCourse->id,
        'name' => 'Welcome',
        'slug' => 'welcome',
    ]);
    Step::factory()->withoutMaterial()->create([
        'lesson_id' => $otherLesson->id,
        'name' => $defaultName,
        'slug' => $baseSlug,
    ]);
    Step::factory()->withoutMaterial()->create([
        'lesson_id' => $otherLesson->id,
        'name' => 'Step 1 copy',
        'slug' => $baseSlug.'-2',
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Welcome',
        'slug' => 'welcome',
    ]);

    $component = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
        ])
        ->assertActionDataSet([
            'name' => $defaultName,
            'slug' => $baseSlug.'-3',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $created = Step::query()->where('lesson_id', $lesson->id)->first();

    expect($created)->not->toBeNull()
        ->and($created->name)->toBe($defaultName)
        ->and($created->slug)->toBe($baseSlug.'-3')
        ->and(Step::query()->where('slug', $created->slug)->count())->toBe(1);

    $saveStep = new ReflectionMethod(CourseCurriculum::class, 'saveStep');
    $saveStep->invoke($component->instance(), [
        'lesson_id' => $lesson->getKey(),
        'name' => 'Handbook',
        'slug' => $baseSlug,
        'text' => null,
        'is_optional' => false,
        'material_type' => null,
        'material_id' => null,
        'require_perfect_score' => false,
    ], [
        'lesson' => $lesson->getKey(),
    ]);

    expect(Step::query()->where('lesson_id', $lesson->id)->where('name', 'Handbook')->value('slug'))
        ->toBe($baseSlug.'-4');
});

it('keeps a generated step slug unique when the step name changes', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $otherCourse = Course::factory()->create();
    $otherLesson = Lesson::factory()->create([
        'course_id' => $otherCourse->id,
        'slug' => 'welcome',
    ]);
    Step::factory()->withoutMaterial()->create([
        'lesson_id' => $otherLesson->id,
        'slug' => 'welcome-introduction',
    ]);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create([
        'course_id' => $course->id,
        'slug' => 'welcome',
    ]);

    $component = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
        ])
        ->set('mountedActions.0.data.name', 'Introduction');

    $component->instance()
        ->getSchema('mountedActionSchema0')
        ->callAfterStateUpdated('mountedActions.0.data.name');

    expect($component->instance()->mountedActions[0]['data']['slug'])->toBe('welcome-introduction-2')
        ->and($component->instance()->mountedActions[0]['data']['slug_is_locked'])->toBeFalse();
});

it('shows the step name before the advanced section', function (): void {
    $components = StepResource::getFormComponents(inCourseEditor: true);

    $nameIndex = null;
    $advancedIndex = null;

    foreach ($components as $index => $component) {
        if ($component instanceof TextInput && $component->getName() === 'name') {
            $nameIndex = $index;
        }

        if ($component instanceof Section && $component->getHeading() === 'Advanced') {
            $advancedIndex = $index;

            expect(collect($component->getDefaultChildComponents())->contains(
                fn (mixed $child): bool => $child instanceof TextInput && $child->getName() === 'name',
            ))->toBeFalse();
        }
    }

    expect($nameIndex)->not->toBeNull()
        ->and($advancedIndex)->not->toBeNull()
        ->and($nameIndex)->toBeLessThan($advancedIndex);
});

it('opens the step editor when the step has no material', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->withoutMaterial()->create([
        'lesson_id' => $lesson->id,
        'name' => 'String manipulation',
    ]);

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $step->getKey(),
        ])
        ->assertSuccessful()
        ->assertSee('String manipulation');
});

it('shows test settings only for a test and limits retry steps to the course', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $other = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $otherLesson = Lesson::factory()->create(['course_id' => $other->id]);
    $test = Test::query()->create(['name' => 'Safety check']);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'video',
        'material_id' => Video::factory()->create()->getKey(),
    ]);
    $sibling = Step::factory()->withoutMaterial()->create(['lesson_id' => $lesson->id]);
    $foreign = Step::factory()->withoutMaterial()->create(['lesson_id' => $otherLesson->id]);

    $testStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'test',
        'material_id' => $test->getKey(),
    ]);

    $videoModal = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $step->getKey(),
        ]);

    expect($videoModal->instance()->mountedActions[0]['data']['material_type'])->toBe('video');

    $testModal = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $testStep->getKey(),
        ]);

    expect($testModal->instance()->mountedActions[0]['data']['material_type'])->toBe('test')
        ->and($testModal->instance()->mountedActions[0]['data'])->toHaveKey('require_perfect_score');

    expect(StepResource::retryStepIdsForLesson($lesson->id, $step->id))
        ->toContain($sibling->id)
        ->not->toContain($step->id)
        ->not->toContain($foreign->id);
});

it('keeps the legacy edit page and relation managers', function (): void {
    config(['filament-lms.course_editor.mode' => 'legacy']);
    useCourseEditorPanel();

    $course = Course::factory()->create(['name' => 'Legacy course']);

    Livewire::test(EditCourse::class, ['record' => $course->getKey()])
        ->assertSuccessful()
        ->assertDontSee('Course outline')
        ->assertDontSee('Save & preview')
        ->assertSee('Assigned Users');

    expect(CourseResource::getRelations())->toHaveCount(3)
        ->and(LessonResource::getNavigationGroup())->toBe('LMS');
});

it('hides lesson step and material resources from navigation when configured', function (): void {
    config(['filament-lms.course_editor.show_content_resources' => false]);

    expect(LessonResource::shouldRegisterNavigation())->toBeFalse()
        ->and(StepResource::shouldRegisterNavigation())->toBeFalse()
        ->and(VideoResource::shouldRegisterNavigation())->toBeFalse()
        ->and(DocumentResource::shouldRegisterNavigation())->toBeFalse()
        ->and(LinkResource::shouldRegisterNavigation())->toBeFalse()
        ->and(TestResource::shouldRegisterNavigation())->toBeFalse()
        ->and(ImageResource::shouldRegisterNavigation())->toBeFalse()
        ->and(CourseResource::shouldRegisterNavigation())->toBeTrue();
});

it('shows an add material tooltip on the step editor plus icon', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'video',
        'material_id' => Video::factory()->create()->getKey(),
    ]);

    $component = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $step->getKey(),
        ]);

    $schemaName = $component->instance()->getMountedActionSchemaName();
    $select = $component->instance()->getSchema($schemaName)->getComponent(
        "{$schemaName}.material_id",
        isAbsoluteKey: true,
    );
    $addMaterial = $select->getSuffixActions()['addMaterial'];

    expect($addMaterial->toHtml())->toContain("content: 'Add material'");
});

it('adds a material from the step editor or uses a recent one', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);
    useCourseEditorPanel();

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $current = Video::factory()->create(['name' => 'Current step video']);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'video',
        'material_id' => $current->getKey(),
    ]);

    Video::factory()->create(['name' => 'Oldest hidden video']);

    foreach (range(1, 10) as $index) {
        Video::factory()->create(['name' => "Recent video {$index}"]);
    }

    $document = Document::query()->create(['name' => 'Handbook']);
    $documentStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'document',
        'material_id' => $document->getKey(),
    ]);

    expect(CourseMaterialForm::recent('video', null)->pluck('name')->all())
        ->toHaveCount(10)
        ->not->toContain('Oldest hidden video')
        ->toContain('Recent video 10')
        ->and(CourseMaterialForm::recent('video', 'Oldest')->pluck('name')->all())->toBe(['Oldest hidden video'])
        ->and(collect(CourseMaterialForm::fields('document'))->map->getName()->all())->toBe(['name', 'file', 'preview'])
        ->and(CourseMaterialForm::canCreate('video'))->toBeTrue()
        ->and(CourseMaterialForm::canCreate('form'))->toBeFalse();

    $chosen = Video::query()->where('name', 'Recent video 4')->first();

    $existing = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $step->getKey(),
        ])
        ->callAction(TestAction::make('addMaterial')->schemaComponent('material_id'), data: [
            'existing_id' => $chosen->getKey(),
        ]);

    expect(Video::query()->count())->toBe(12)
        ->and($existing->instance()->mountedActions[0]['data']['material_id'])->toEqual($chosen->getKey());

    $createdFromModal = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $step->getKey(),
        ])
        ->callAction(TestAction::make('addMaterial')->schemaComponent('material_id'), data: [
            'name' => 'Brand new video',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

    $created = Video::query()->where('name', 'Brand new video')->first();

    expect($created)->not->toBeNull()
        ->and($created->url)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
        ->and($createdFromModal->instance()->mountedActions[0]['data']['material_id'])->toEqual($created->getKey());

    $usedDocument = Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->mountAction('editStep', arguments: [
            'lesson' => $lesson->getKey(),
            'step' => $documentStep->getKey(),
        ])
        ->callAction(TestAction::make('addMaterial')->schemaComponent('material_id'), data: [
            'existing_id' => $document->getKey(),
        ]);

    expect($usedDocument->instance()->mountedActions[0]['data']['material_id'])->toEqual($document->getKey())
        ->and(Document::query()->count())->toBe(1);
});

it('shortens existing material links and skips materials without a url', function (): void {
    $wasStrict = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();

    try {
        $longUrl = 'https://drive.google.com/file/d/'.str_repeat('a', 80);
        $video = new Video;
        $video->setRawAttributes(['id' => 1, 'name' => 'Clip', 'url' => $longUrl]);

        $detail = CourseMaterialForm::existingDetail($video);
        $visible = trim(strip_tags($detail->toHtml()));

        expect($visible)->toBe(CourseMaterialForm::urlPreviewLabel($longUrl))
            ->and(mb_strlen($visible))->toBeLessThanOrEqual(CourseMaterialForm::URL_PREVIEW_LENGTH)
            ->and($visible)->not->toContain(str_repeat('a', 40))
            ->and($detail->toHtml())->toContain('x-tooltip')
            ->and($detail->toHtml())->toContain(str_repeat('a', 80));

        foreach ([new Document, new Test, new Image] as $material) {
            $material->setRawAttributes(['id' => 1, 'name' => 'Item']);

            expect(CourseMaterialForm::existingDetail($material))->toBeNull();
        }

        $document = Document::query()->create(['name' => 'Handbook']);
        $storedVideo = Video::factory()->create([
            'name' => 'Stored clip',
            'url' => $longUrl,
        ]);

        expect(CourseMaterialForm::existingDetail($document->fresh()))->toBeNull()
            ->and(strip_tags(CourseMaterialForm::existingDetail($storedVideo->fresh())->toHtml()))
            ->toContain('...');
    } finally {
        Model::preventAccessingMissingAttributes($wasStrict);
    }
});

it('keeps lesson step and material resources in the lms navigation group', function (): void {
    config(['filament-lms.course_editor.mode' => 'builder']);

    expect(LessonResource::getNavigationGroup())->toBe('LMS')
        ->and(StepResource::getNavigationGroup())->toBe('LMS')
        ->and(VideoResource::getNavigationGroup())->toBe('LMS')
        ->and(DocumentResource::getNavigationGroup())->toBe('LMS')
        ->and(LinkResource::getNavigationGroup())->toBe('LMS')
        ->and(TestResource::getNavigationGroup())->toBe('LMS')
        ->and(ImageResource::getNavigationGroup())->toBe('LMS')
        ->and(CourseResource::getNavigationGroup())->toBe('LMS')
        ->and(CreditCategoryResource::getNavigationGroup())->toBe('LMS')
        ->and(CourseResource::getRelations())->toBe([]);
});

it('reads course editor labels from the package language file', function (): void {
    expect(__('filament-lms::course-editor.sections.general.heading'))->toBe('General')
        ->and(__('filament-lms::course-editor.sections.general.description'))->toBe('Basic course information');

    Lang::addLines([
        'course-editor.sections.general.heading' => 'Overview',
        'course-editor.sections.general.description' => 'Course basics',
    ], 'en', 'filament-lms');

    $general = CourseEditorForm::builderComponents()[1]->getDefaultChildComponents()[0];

    expect($general->getHeading())->toBe('Overview')
        ->and($general->getDescription())->toBe('Course basics');
});

it('scopes the outline to the current tenant and stamps the tenant column', function (): void {
    Schema::create('course_editor_tenants', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::table('lms_courses', function (Blueprint $table): void {
        $table->unsignedBigInteger('company_id')->nullable();
    });
    Schema::table('lms_lessons', function (Blueprint $table): void {
        $table->unsignedBigInteger('company_id')->nullable();
    });
    Schema::table('lms_steps', function (Blueprint $table): void {
        $table->unsignedBigInteger('company_id')->nullable();
    });

    config([
        'filament-lms.course_editor.mode' => 'builder',
        'filament-lms.tenancy.enabled' => true,
        'filament-lms.tenancy.column' => 'company_id',
        'filament-lms.tenancy.model' => CourseEditorTenant::class,
    ]);

    foreach ([Course::class, Lesson::class, Step::class] as $model) {
        if (! $model::hasGlobalScope('filament_lms_tenancy')) {
            $model::addGlobalScope('filament_lms_tenancy', new TenantScope);
        }
    }

    useCourseEditorPanel();

    $alpha = CourseEditorTenant::query()->create(['name' => 'Alpha']);
    $beta = CourseEditorTenant::query()->create(['name' => 'Beta']);
    Filament::setTenant($alpha, isQuiet: true);

    $course = Course::factory()->create(['company_id' => $alpha->getKey()]);
    Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Alpha lesson',
        'company_id' => $alpha->getKey(),
    ]);
    Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Beta lesson',
        'company_id' => $beta->getKey(),
    ]);

    Livewire::test(CourseCurriculum::class, ['record' => $course])
        ->assertSee('Alpha lesson')
        ->assertDontSee('Beta lesson')
        ->callAction('createLesson', data: [
            'name' => 'Created lesson',
            'slug' => 'created-lesson',
        ]);

    expect(Lesson::query()->where('slug', 'created-lesson')->first())
        ->company_id->toBe($alpha->getKey());
});

class CourseEditorTenant extends Model
{
    protected $table = 'course_editor_tenants';

    protected $guarded = [];
}
