<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Helpers\TenantHelper;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;

final class CommonCartridgeImportService
{
    /** @var list<Document> */
    private array $packageDocuments = [];

    private ?string $packageLaunchHref = null;

    public function __construct(
        private readonly ManifestParser $parser,
        private readonly ScormPackageStorage $packageStorage,
    ) {}

    /**
     * Items that may still require manual configuration after import.
     *
     * @return list<string>
     */
    public static function manualImportGaps(): array
    {
        return [
            'Assessments import only the first question; add additional fields in Form Builder if needed.',
            'Articulate Rise courses import as a single step (no per-block lesson structure).',
            'Enable embedded player mode on the course for SCORM/HTML5 completion sync after import.',
            'Assign learners to the course via course users when using private courses or restricted visibility.',
        ];
    }

    /**
     * Import a course from an extracted Common Cartridge / SCORM package.
     *
     * @param  int|string|null  $tenantId  Optional tenant ID when tenancy is enabled.
     * @return array{course: Course, lessons_created: int, steps_created: int}
     */
    public function import(string $extractedPath, int|string|null $tenantId = null): array
    {
        $this->packageDocuments = [];
        $this->packageLaunchHref = null;

        try {
            Log::channel('single')->info('CC import: start', [
                'context' => 'cc-import',
                'extracted_path' => mb_rtrim($extractedPath, '/'),
            ]);

            $manifest = $this->parser->parse($extractedPath);
            $extractedPath = mb_rtrim($extractedPath, '/');
            $this->packageLaunchHref = $manifest->preferredLaunchHref;

            return DB::transaction(function () use ($manifest, $extractedPath, $tenantId) {
                $course = $this->createCourse($manifest, $tenantId);
                $lessonsCreated = 0;
                $stepsCreated = 0;
                $isFirstStepOfCourse = true;
                $primaryResourceId = $this->getPrimaryWebContentResourceId(
                    $manifest->resources,
                    $manifest->preferredLaunchHref,
                );

                foreach ($manifest->lessons as $lessonStructure) {
                    $lesson = $this->createLesson($course, $lessonStructure);
                    $lessonsCreated++;

                    foreach ($lessonStructure->steps as $stepStructure) {
                        $this->createStep(
                            $course,
                            $lesson,
                            $stepStructure,
                            $manifest->resources,
                            $extractedPath,
                            $manifest->preferredLaunchHref,
                            $isFirstStepOfCourse ? $primaryResourceId : null,
                        );
                        $isFirstStepOfCourse = false;
                        $stepsCreated++;
                    }

                    $stepsCreated += $this->createResourcesStepIfNeeded($course, $lesson, $lessonStructure, $manifest->frameResources);
                }

                $this->attachRetainedPackage($extractedPath);
                $this->finalizeEmbeddedPlayerCourse($course, $extractedPath);

                return [
                    'course' => $course,
                    'lessons_created' => $lessonsCreated,
                    'steps_created' => $stepsCreated,
                ];
            });
        } finally {
            $this->packageDocuments = [];
            $this->packageLaunchHref = null;
        }
    }

    private function finalizeEmbeddedPlayerCourse(Course $course, string $extractedPath): void
    {
        if ($this->packageDocuments === []) {
            return;
        }

        $extractedPath = mb_rtrim($extractedPath, '/');
        $completionMode = is_file($extractedPath.'/imsmanifest.xml')
            ? CompletionMode::Scorm12
            : CompletionMode::Html5;

        $course->update([
            'embedded_player' => true,
            'completion_mode' => $completionMode,
        ]);
    }

    private function attachRetainedPackage(string $extractedPath): void
    {
        if ($this->packageDocuments === []) {
            return;
        }

        $package = $this->packageStorage->retainPackage($extractedPath);
        if ($package === null) {
            return;
        }

        foreach ($this->packageDocuments as $document) {
            $document->update([
                'package_disk' => $package['disk'],
                'package_path' => $package['path'],
                'package_launch_path' => $this->packageLaunchHref,
            ]);
        }
    }

    private function createCourse(ParsedManifest $manifest, int|string|null $tenantId): Course
    {
        $slug = $this->uniqueCourseSlug(Str::slug($manifest->courseTitle), $tenantId);
        $externalId = $this->uniqueCourseColumn(Str::slug($manifest->courseTitle, '_'), 'external_id', $tenantId);
        $name = $this->uniqueCourseColumn($manifest->courseTitle, 'name', $tenantId);

        $awards = config('filament-lms.awards', ['default' => 'Default']);
        $defaultAward = array_key_first($awards);

        $data = [
            'name' => $name,
            'slug' => $slug,
            'external_id' => $externalId,
            'description' => $manifest->courseDescription,
            'is_private' => false,
            'award' => $defaultAward,
        ];

        if (config('filament-lms.tenancy.enabled') && $tenantId !== null) {
            $data[TenantHelper::getTenantColumnName()] = $tenantId;
        }

        return Course::query()->create($data);
    }

    private function createLesson(Course $course, LessonStructure $structure): Lesson
    {
        $slug = $this->uniqueSlugForLesson($course, Str::slug($structure->title));

        return Lesson::query()->create([
            'course_id' => $course->id,
            'order' => $structure->order,
            'name' => $structure->title,
            'slug' => $slug,
        ]);
    }

    /**
     * When using Articulate frame.xml, steps have no resourceIdentifier; we attach the main SCO to the first step.
     *
     * @param  array<string, ResourceData>  $resources
     */
    private function createStep(
        Course $course,
        Lesson $lesson,
        StepStructure $structure,
        array $resources,
        string $extractedPath,
        ?string $manifestPreferredLaunchHref,
        ?string $primaryResourceIdForFirstStep = null,
    ): void {
        $slug = $this->uniqueSlugForStep($lesson, Str::slug($structure->title));
        $materialId = null;
        $materialType = null;
        $text = null;

        $nodeScriptPath = function_exists('base_path')
            ? base_path('scripts/extract-articulate-slide-data.cjs')
            : null;
        $nodeScriptAvailable = $nodeScriptPath !== null && is_file($nodeScriptPath);
        if ($structure->slideId !== null && $structure->order === 0) {
            Log::channel('single')->info('CC import: node extractor config', [
                'context' => 'cc-import',
                'node_script_path' => $nodeScriptPath,
                'node_script_exists' => $nodeScriptPath !== null ? is_file($nodeScriptPath) : false,
                'node_script_will_use' => $nodeScriptAvailable,
            ]);
        }
        $extractor = new ArticulateSlideContentExtractor($nodeScriptAvailable ? $nodeScriptPath : null);
        $slideData = $structure->slideId !== null ? $extractor->getSlideData($extractedPath, $structure->slideId) : null;
        $isAssessment = $slideData !== null && $extractor->getSlideTitle($slideData) === 'Assessment';

        $slideJsPath = $structure->slideId !== null
            ? mb_rtrim($extractedPath, '/').'/html5/data/js/'.basename($structure->slideId).'.js'
            : null;
        if ($structure->slideId !== null) {
            Log::channel('single')->info('CC import: step slide', [
                'context' => 'cc-import',
                'step_title' => $structure->title,
                'slide_id' => $structure->slideId,
                'slide_js_path' => $slideJsPath,
                'slide_js_exists' => $slideJsPath !== null && is_file($slideJsPath),
                'slide_data_loaded' => $slideData !== null,
                'slide_title' => $slideData !== null ? $extractor->getSlideTitle($slideData) : null,
                'is_assessment' => $isAssessment,
            ]);
        }

        $resourceId = $structure->resourceIdentifier ?? $primaryResourceIdForFirstStep;
        if (! $isAssessment && $resourceId !== null && isset($resources[$resourceId])) {
            $resource = $resources[$resourceId];
            $launchHref = $this->resolveLaunchHref($resource, $extractedPath, $manifestPreferredLaunchHref);

            if ($launchHref !== null && is_file($extractedPath.'/'.$launchHref)) {
                $primaryPath = $extractedPath.'/'.$launchHref;
                $docData = [
                    'name' => $structure->title !== '' ? $structure->title : basename($launchHref),
                ];
                if (config('filament-lms.tenancy.enabled') && $course->getAttribute(TenantHelper::getTenantColumnName())) {
                    $docData[TenantHelper::getTenantColumnName()] = $course->getAttribute(TenantHelper::getTenantColumnName());
                }
                $document = Document::query()->create($docData);
                $document->addMedia($primaryPath)
                    ->preservingOriginal()
                    ->toMediaCollection('default');
                $this->packageDocuments[] = $document;
                if ($this->packageLaunchHref === null) {
                    $this->packageLaunchHref = $launchHref;
                }
                $materialId = $document->id;
                $materialType = 'document';
            } else {
                $text = "Imported resource: {$resource->type} (".basename($resource->href).'). Configure material in admin if needed.';
            }
        }

        if ($slideData !== null) {
            if ($isAssessment) {
                $intro = $extractor->extractAssessmentIntroFromSlideData($slideData);
                $text = $intro !== '' ? $intro : null;
            } else {
                $extracted = $extractor->extractFromSlideData($slideData);
                if ($extracted !== '') {
                    $text = $extracted;
                }
            }
        }
        // Always persist slide-extracted text when present (text field is the source for step content).

        $firstQuestionAndOptions = $isAssessment
            ? $extractor->getAssessmentFirstQuestionAndOptions($slideData)
            : null;
        $formName = $firstQuestionAndOptions !== null
            ? $firstQuestionAndOptions['question']
            : ($structure->title !== '' ? $structure->title : 'Assessment');

        if ($structure->slideId !== null) {
            Log::channel('single')->info('CC import: step result', [
                'context' => 'cc-import',
                'step_title' => $structure->title,
                'text_length' => $text !== null ? strlen($text) : 0,
            ]);
        }

        if ($isAssessment && class_exists(FilamentForm::class)) {
            $formData = ['name' => $formName];
            $formClass = FilamentForm::class;
            if (config('filament-form-builder.tenancy.enabled', false)) {
                $formTenantColumn = $formClass::getTenantColumnName();
                $courseTenantValue = $course->getAttribute(TenantHelper::getTenantColumnName());
                if ($courseTenantValue !== null) {
                    $formData[$formTenantColumn] = $courseTenantValue;
                }
            }
            $form = $formClass::query()->create($formData);

            $fieldClass = FilamentFormField::class;
            if (class_exists($fieldClass) && $firstQuestionAndOptions !== null && $firstQuestionAndOptions['options'] !== []) {
                $options = [];
                foreach ($firstQuestionAndOptions['options'] as $label) {
                    $options[] = ['value' => $label, 'label' => $label];
                }
                $fieldClass::query()->create([
                    'filament_form_id' => $form->id,
                    'order' => 0,
                    'type' => 'Select One',
                    'label' => $firstQuestionAndOptions['question'],
                    'required' => true,
                    'options' => $options,
                ]);
            }

            $testData = [
                'name' => $formName,
                'filament_form_id' => $form->id,
            ];
            if (config('filament-lms.tenancy.enabled') && $course->getAttribute(TenantHelper::getTenantColumnName())) {
                $testData[TenantHelper::getTenantColumnName()] = $course->getAttribute(TenantHelper::getTenantColumnName());
            }
            $test = Test::query()->create($testData);
            $materialId = $test->id;
            $materialType = 'test';
            Log::channel('single')->info('CC import: created test material for assessment', [
                'context' => 'cc-import',
                'step_title' => $structure->title,
                'test_id' => $test->id,
                'form_id' => $form->id,
            ]);
        }

        Step::query()->create([
            'lesson_id' => $lesson->id,
            'order' => $structure->order,
            'name' => $structure->title !== '' ? $structure->title : 'Step '.($structure->order + 1),
            'slug' => $slug,
            'material_id' => $materialId,
            'material_type' => $materialType,
            'text' => $text,
            'player_slide_id' => $structure->slideId,
            'retry_step_id' => null,
            'require_perfect_score' => false,
        ]);
    }

    private function uniqueCourseSlug(string $base, int|string|null $tenantId): string
    {
        return $this->uniqueCourseColumn($base, 'slug', $tenantId);
    }

    private function uniqueCourseColumn(string $base, string $column, int|string|null $tenantId): string
    {
        $value = $base;
        $i = 0;
        $query = Course::query();
        if (config('filament-lms.tenancy.enabled') && $tenantId !== null) {
            $query->where(TenantHelper::getTenantColumnName(), $tenantId);
        }
        while ($query->clone()->where($column, $value)->exists()) {
            $i++;
            $value = $base.'-'.$i;
        }

        return $value;
    }

    /**
     * Returns the first webcontent resource identifier (main SCO entry, e.g. index_lms.html) or null.
     *
     * @param  array<string, ResourceData>  $resources
     */
    /**
     * @param  array<string, ResourceData>  $resources
     */
    private function getPrimaryWebContentResourceId(array $resources, ?string $preferredLaunchHref): ?string
    {
        if ($preferredLaunchHref !== null) {
            foreach ($resources as $identifier => $resource) {
                if (in_array($preferredLaunchHref, $resource->fileHrefs, true) || $resource->href === $preferredLaunchHref) {
                    return $identifier;
                }
            }
        }

        foreach ($resources as $identifier => $resource) {
            if (in_array(mb_strtolower(mb_trim($resource->type)), ['webcontent', 'associatedcontent'], true)
                && $resource->href !== '') {
                return $identifier;
            }
        }

        return null;
    }

    private function resolveLaunchHref(ResourceData $resource, string $extractedPath, ?string $manifestPreferred): ?string
    {
        if ($manifestPreferred !== null && is_file($extractedPath.'/'.$manifestPreferred)) {
            return $manifestPreferred;
        }

        foreach (['scormcontent/index.html', 'index_lms.html', 'index.html', 'story.html'] as $candidate) {
            if (in_array($candidate, $resource->fileHrefs, true) && is_file($extractedPath.'/'.$candidate)) {
                return $candidate;
            }
        }

        if ($resource->href !== '' && is_file($extractedPath.'/'.$resource->href)) {
            return $resource->href;
        }

        return null;
    }

    private function uniqueSlugForLesson(Course $course, string $base): string
    {
        $slug = $base;
        $i = 0;
        while ($course->lessons()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function uniqueSlugForStep(Lesson $lesson, string $base): string
    {
        $slug = $base;
        $i = 0;
        while ($lesson->steps()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Create a "Resources" step for the lesson when frame resources match the lesson title (e.g. "Session 1").
     *
     * @param  list<FrameResourceEntry>  $frameResources
     * @return int Number of steps created (0 or 1)
     */
    private function createResourcesStepIfNeeded(
        Course $course,
        Lesson $lesson,
        LessonStructure $lessonStructure,
        array $frameResources,
    ): int {
        if ($frameResources === []) {
            return 0;
        }

        $lessonTitle = $lessonStructure->title;
        $matches = [];
        foreach ($frameResources as $entry) {
            $t = $entry->title;
            if (str_starts_with($t, $lessonTitle.':') || str_starts_with($t, $lessonTitle.' ') || $t === $lessonTitle) {
                $matches[] = $entry;
            }
        }
        if ($matches === []) {
            return 0;
        }

        // Build HTML list only; do not create Link models so we avoid dispatching screenshot jobs
        // (Browsershot often fails for Google Drive / external URLs and would spam the log).
        $items = [];
        foreach ($matches as $entry) {
            $items[] = '<li><a href="'.htmlspecialchars($entry->url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">'.htmlspecialchars($entry->title, ENT_QUOTES, 'UTF-8').'</a></li>';
        }
        $resourcesHtml = '<ul class="list-disc pl-6 space-y-1">'.implode('', $items).'</ul>';
        $slug = $this->uniqueSlugForStep($lesson, 'resources');
        $order = $lesson->steps()->max('order') + 1;

        Step::query()->create([
            'lesson_id' => $lesson->id,
            'order' => $order,
            'name' => 'Resources',
            'slug' => $slug,
            'material_id' => null,
            'material_type' => null,
            'text' => $resourcesHtml,
            'retry_step_id' => null,
            'require_perfect_score' => false,
        ]);

        return 1;
    }
}
