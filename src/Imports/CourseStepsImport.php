<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Services\VideoUrlService;

class CourseStepsImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected string $courseName
    ) {}

    /**
     * Extract URL from cell that may be "label (url)" or just "url".
     */
    public static function extractUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/\((\s*https?:\/\/[^)]+)\s*\)/', $value, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^https?:\/\//', $value)) {
            return $value;
        }

        return null;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $first = $rows->first();
        $headers = array_keys($first->toArray());
        $hasStepNameColumn = in_array('step_name', $headers, true);
        $hasScriptColumn = in_array('script', $headers, true);

        if (! $hasStepNameColumn && ! in_array('lesson_name', $headers, true)) {
            throw new \InvalidArgumentException('Unrecognized CSV format. Expected at least "Step Name" or "Lesson Name" column.');
        }

        DB::transaction(function () use ($rows, $hasStepNameColumn, $hasScriptColumn): void {
            $this->importRows($rows, $hasStepNameColumn, $hasScriptColumn);
        });
    }

    protected function importRows(Collection $rows, bool $hasStepNameColumn, bool $hasScriptColumn): void
    {
        $course = Course::create([
            'name' => $this->courseName,
            'slug' => Str::slug($this->courseName),
            'external_id' => Str::slug($this->courseName, '_'),
        ]);

        $lessonOrder = 0;
        $lessons = [];
        $defaultLesson = null;

        foreach ($rows as $row) {
            $stepName = $this->value($row, 'step_name') ?? $this->value($row, 'lesson_name');
            if ($stepName === null || trim((string) $stepName) === '') {
                continue;
            }

            $stepName = trim((string) $stepName);

            if ($hasStepNameColumn) {
                $lessonName = $this->value($row, 'lesson_name');
                $lessonName = $lessonName !== null ? trim((string) $lessonName) : $this->courseName;
                if (! isset($lessons[$lessonName])) {
                    $lessonOrder++;
                    $lessons[$lessonName] = Lesson::create([
                        'course_id' => $course->id,
                        'name' => $lessonName,
                        'slug' => Str::slug($lessonName),
                        'order' => $lessonOrder,
                    ]);
                }
                $lesson = $lessons[$lessonName];
                $videoUrl = $this->value($row, 'url');
                $videoUrl = $videoUrl !== null && trim((string) $videoUrl) !== '' ? trim((string) $videoUrl) : null;
                $text = null;
                $imageUrl = null;
                $linkUrl = null;
            } else {
                if ($defaultLesson === null) {
                    $defaultLesson = Lesson::create([
                        'course_id' => $course->id,
                        'name' => $this->courseName,
                        'slug' => Str::slug($this->courseName),
                        'order' => 1,
                    ]);
                }
                $lesson = $defaultLesson;
                $text = $hasScriptColumn ? $this->value($row, 'script') : null;
                $text = $text !== null ? trim((string) $text) : null;
                $videoUrl = self::extractUrl($this->value($row, 'video_audio_image'));
                $imageUrl = self::extractUrl($this->value($row, 'slides_image'));
                $linkUrlRaw = $this->value($row, 'url');
                $linkUrl = $linkUrlRaw !== null && trim((string) $linkUrlRaw) !== ''
                    ? self::extractUrl($linkUrlRaw) ?? trim((string) $linkUrlRaw)
                    : null;
            }

            [$materialId, $materialType] = $this->createMaterialForStep(
                $stepName,
                $videoUrl,
                $imageUrl,
                $linkUrl
            );

            $stepOrder = $lesson->steps()->count() + 1;
            $stepSlug = $lesson->slug.'-'.Str::slug($stepName);

            Step::create([
                'lesson_id' => $lesson->id,
                'order' => $stepOrder,
                'name' => $stepName,
                'slug' => $stepSlug,
                'text' => $text,
                'material_id' => $materialId,
                'material_type' => $materialType,
            ]);
        }
    }

    /**
     * Create video, image, or link material (priority: video > image > link). Returns [material_id, material_type].
     *
     * @return array{0: int|null, 1: string|null}
     */
    protected function createMaterialForStep(
        string $stepName,
        ?string $videoUrl,
        ?string $imageUrl,
        ?string $linkUrl
    ): array {
        if ($videoUrl !== null && $videoUrl !== '') {
            $videoUrl = VideoUrlService::convertToEmbedUrl($videoUrl);
            $video = Video::create([
                'name' => $stepName,
                'url' => $videoUrl,
            ]);

            return [$video->id, 'video'];
        }

        if ($imageUrl !== null && $imageUrl !== '') {
            $image = Image::create(['name' => $stepName]);
            try {
                $image->addMediaFromUrl($imageUrl)->toMediaCollection('image');
            } catch (\Throwable) {
                // If URL media add fails, step still has image record
            }

            return [$image->id, 'image'];
        }

        if ($linkUrl !== null && $linkUrl !== '') {
            $link = Link::create([
                'name' => $stepName,
                'url' => $linkUrl,
            ]);

            return [$link->id, 'link'];
        }

        return [null, null];
    }

    protected function value(Collection $row, string $key): mixed
    {
        return $row->get($key);
    }
}
