<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Tapp\FilamentLms\Notifications\CommonCartridgeImportCompletedNotification;
use Tapp\FilamentLms\Services\CommonCartridge\CommonCartridgeImportService;
use Throwable;
use ZipArchive;

final class ImportCommonCartridgeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $storedPath,
        public readonly int $userId,
        public readonly int|string|null $tenantId = null,
    ) {}

    public function handle(CommonCartridgeImportService $importService): void
    {
        $extractPath = null;

        try {
            if (! is_file($this->storedPath)) {
                throw new RuntimeException('Stored import file not found: '.$this->storedPath);
            }

            $extractPath = $this->extractZip($this->storedPath);

            $root = mb_rtrim($extractPath, '/');
            Log::channel('single')->info('CC import: package root', [
                'context' => 'cc-import',
                'package_root' => $root,
                'imsmanifest_exists' => is_file($root.'/imsmanifest.xml'),
                'frame_xml_exists' => is_file($root.'/story_content/frame.xml'),
                'sample_slide_js_exists' => is_file($root.'/html5/data/js/6FA6ZHMtWms.js'),
            ]);

            $result = $importService->import($extractPath, $this->tenantId);

            $user = $this->getUser();
            $user?->notify(new CommonCartridgeImportCompletedNotification(
                success: true,
                message: "Course \"{$result['course']->name}\" imported: {$result['lessons_created']} lesson(s), {$result['steps_created']} step(s).",
                courseName: $result['course']->name,
            ));

            $this->deleteStoredFileIfConfigured();
        } catch (Throwable $e) {
            Log::error('Common Cartridge import failed', [
                'path' => $this->storedPath,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $user = $this->getUser();
            $user?->notify(new CommonCartridgeImportCompletedNotification(
                success: false,
                message: 'Import failed: '.$e->getMessage(),
                courseName: null,
            ));

            throw $e;
        } finally {
            if ($extractPath !== null && is_dir($extractPath)) {
                $this->deleteDirectory($extractPath);
            }
        }
    }

    private function extractZip(string $zipPath): string
    {
        $extractPath = storage_path('app/temp/cc-import-'.Str::uuid()->toString());
        if (! is_dir(dirname($extractPath))) {
            mkdir(dirname($extractPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Could not open ZIP file.');
        }
        $zip->extractTo($extractPath);
        $zip->close();

        return $this->normalizePackageRoot($extractPath);
    }

    /**
     * When the zip has a single root directory (e.g. "COS_SCORM12") that contains imsmanifest.xml,
     * use that subdirectory as the package root so the parser finds frame.xml and html5/data/js.
     */
    private function normalizePackageRoot(string $extractPath): string
    {
        $manifestPath = mb_rtrim($extractPath, '/').'/imsmanifest.xml';
        if (is_file($manifestPath)) {
            return mb_rtrim($extractPath, '/');
        }
        $entries = @scandir($extractPath);
        if ($entries === false) {
            return mb_rtrim($extractPath, '/');
        }
        $dirs = array_filter($entries, function ($name) use ($extractPath) {
            return $name !== '.' && $name !== '..' && is_dir(mb_rtrim($extractPath, '/').'/'.$name);
        });
        if (count($dirs) === 1) {
            $subDir = mb_rtrim($extractPath, '/').'/'.reset($dirs);
            $nestedManifest = $subDir.'/imsmanifest.xml';
            if (is_file($nestedManifest)) {
                return $subDir;
            }
        }

        return mb_rtrim($extractPath, '/');
    }

    private function deleteDirectory(string $path): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }
        rmdir($path);
    }

    private function deleteStoredFileIfConfigured(): void
    {
        if (config('filament-lms.common_cartridge_import.delete_after_success', true) && is_file($this->storedPath)) {
            @unlink($this->storedPath);
        }
    }

    private function getUser(): ?object
    {
        $userModel = config('filament-lms.user_model');

        return $userModel ? $userModel::find($this->userId) : null;
    }
}
