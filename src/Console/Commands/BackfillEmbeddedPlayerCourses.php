<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;

final class BackfillEmbeddedPlayerCourses extends Command
{
    private const SCORM_DRIVER_LAUNCH = 'scormdriver/indexAPI.html';

    protected $signature = 'filament-lms:backfill-embedded-player
                            {--course-id= : Limit to a single course id}';

    protected $description = 'Enable embedded player mode on courses that have retained SCORM/HTML5 packages';

    public function handle(): int
    {
        $documentIds = Document::query()
            ->whereNotNull('package_path')
            ->where('package_path', '!=', '')
            ->pluck('id');

        if ($documentIds->isEmpty()) {
            $this->warn('No documents with retained packages found.');

            return self::SUCCESS;
        }

        $query = Course::query()
            ->whereHas('steps', function ($stepQuery) use ($documentIds): void {
                $stepQuery
                    ->where('material_type', 'document')
                    ->whereIn('material_id', $documentIds);
            });

        if ($courseId = $this->option('course-id')) {
            $query->whereKey($courseId);
        }

        $updated = 0;
        foreach ($query->with(['steps.material'])->get() as $course) {
            $completionMode = $this->resolveCompletionModeForCourse($course);
            $course->update([
                'embedded_player' => true,
                'completion_mode' => $completionMode,
            ]);

            $this->updateDocumentLaunchPaths($course);

            $updated++;
            $this->line("Updated course [{$course->id}] {$course->name} ({$completionMode->value})");
        }

        $this->info("Updated {$updated} course(s).");

        return self::SUCCESS;
    }

    private function resolveCompletionModeForCourse(Course $course): CompletionMode
    {
        foreach ($course->steps as $step) {
            $document = $step->material;
            if (! $document instanceof Document || ! $document->hasScormPackage()) {
                continue;
            }

            if ($this->packageHasImsManifest($document)) {
                return CompletionMode::Scorm12;
            }
        }

        if ($course->completion_mode !== CompletionMode::Native) {
            return $course->completion_mode;
        }

        return CompletionMode::Html5;
    }

    private function updateDocumentLaunchPaths(Course $course): void
    {
        foreach ($course->steps as $step) {
            $document = $step->material;
            if (! $document instanceof Document || ! $document->hasScormPackage()) {
                continue;
            }

            if (! $this->packageFileExists($document, self::SCORM_DRIVER_LAUNCH)) {
                continue;
            }

            if ($document->package_launch_path === self::SCORM_DRIVER_LAUNCH) {
                continue;
            }

            $document->update(['package_launch_path' => self::SCORM_DRIVER_LAUNCH]);
            $this->line("  → document [{$document->id}] launch path set to ".self::SCORM_DRIVER_LAUNCH);
        }
    }

    private function packageHasImsManifest(Document $document): bool
    {
        return $this->packageFileExists($document, 'imsmanifest.xml');
    }

    private function packageFileExists(Document $document, string $relativePath): bool
    {
        $packageRoot = $this->resolvePackageRoot($document);
        if ($packageRoot === null) {
            return false;
        }

        $fullPath = $packageRoot.'/'.ltrim(str_replace('\\', '/', $relativePath), '/');
        $realPackageRoot = realpath($packageRoot);
        $realFile = realpath($fullPath);

        if ($realPackageRoot === false || $realFile === false) {
            return false;
        }

        return str_starts_with($realFile, $realPackageRoot) && is_file($realFile);
    }

    private function resolvePackageRoot(Document $document): ?string
    {
        $packagePath = $document->package_path;
        if ($packagePath === null || $packagePath === '') {
            return null;
        }

        $disk = $document->package_disk ?: (string) config('filament-lms.common_cartridge_import.storage_disk', 'local');
        $configuredRoot = Storage::disk($disk)->path($packagePath);

        if (is_dir($configuredRoot)) {
            return $configuredRoot;
        }

        $legacyRoot = storage_path('app/'.$packagePath);

        return is_dir($legacyRoot) ? $legacyRoot : null;
    }
}
