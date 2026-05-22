<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;

final class BackfillEmbeddedPlayerCourses extends Command
{
    protected $signature = 'filament-lms:backfill-embedded-player
                            {--course-id= : Limit to a single course id}';

    protected $description = 'Enable embedded player mode on courses that have retained SCORM/HTML5 packages';

    public function handle(): int
    {
        $courseIds = Document::query()
            ->whereNotNull('package_path')
            ->where('package_path', '!=', '')
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            $this->warn('No documents with retained packages found.');

            return self::SUCCESS;
        }

        $query = Course::query()
            ->whereHas('steps', function ($stepQuery) use ($courseIds): void {
                $stepQuery
                    ->where('material_type', 'document')
                    ->whereIn('material_id', $courseIds);
            });

        if ($courseId = $this->option('course-id')) {
            $query->whereKey($courseId);
        }

        $updated = 0;
        foreach ($query->get() as $course) {
            $course->update([
                'embedded_player' => true,
                'completion_mode' => $course->completion_mode === CompletionMode::Native
                    ? CompletionMode::Html5
                    : $course->completion_mode,
            ]);
            $updated++;
            $this->line("Updated course [{$course->id}] {$course->name}");
        }

        $this->info("Updated {$updated} course(s). Re-import SCORM 1.2 zips for full API completion sync.");

        return self::SUCCESS;
    }
}
