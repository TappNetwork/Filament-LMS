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
        $tempRootPath = '';

        try {
            if (! is_file($this->storedPath)) {
                throw new RuntimeException('Stored import file not found: '.$this->storedPath);
            }

            $extractPath = $this->extractZip($this->storedPath, $tempRootPath);

            $root = mb_rtrim($extractPath, '/');
            Log::channel('single')->info('CC import: package root', [
                'context' => 'cc-import',
                'package_root' => $root,
                'imsmanifest_exists' => is_file($root.'/imsmanifest.xml'),
                'frame_xml_exists' => is_file($root.'/story_content/frame.xml'),
            ]);

            $result = $importService->import($extractPath, $this->tenantId);

            $user = $this->getUser();
            $gaps = implode(' ', CommonCartridgeImportService::manualImportGaps());
            $user?->notify(new CommonCartridgeImportCompletedNotification(
                success: true,
                message: "Course \"{$result['course']->name}\" imported: {$result['lessons_created']} lesson(s), {$result['steps_created']} step(s). Manual follow-up may be needed: {$gaps}",
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
            if ($tempRootPath !== '' && is_dir($tempRootPath)) {
                $this->deleteDirectory($tempRootPath);
            }
        }
    }

    private function extractZip(string $zipPath, string &$tempRootPath): string
    {
        $extractPath = storage_path('app/temp/cc-import-'.Str::uuid()->toString());
        $tempRootPath = $extractPath;

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
     * When the zip has a single root directory (e.g. "COS_SCORM12") that contains package markers,
     * use that subdirectory as the package root so the parser finds imsmanifest.xml or frame.xml.
     */
    private function normalizePackageRoot(string $extractPath): string
    {
        $root = mb_rtrim($extractPath, '/');
        if ($this->hasPackageRootMarkers($root)) {
            return $root;
        }

        $entries = @scandir($extractPath);
        if ($entries === false) {
            return $root;
        }
        $dirs = array_filter($entries, function ($name) use ($extractPath) {
            return $name !== '.' && $name !== '..' && is_dir(mb_rtrim($extractPath, '/').'/'.$name);
        });
        if (count($dirs) === 1) {
            $subDir = $root.'/'.reset($dirs);
            if ($this->hasPackageRootMarkers($subDir)) {
                return $subDir;
            }
        }

        return $root;
    }

    private function hasPackageRootMarkers(string $path): bool
    {
        $root = mb_rtrim($path, '/');

        return is_file($root.'/imsmanifest.xml') || is_file($root.'/story_content/frame.xml');
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
