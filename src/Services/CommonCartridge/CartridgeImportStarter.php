<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;
use Throwable;

final class CartridgeImportStarter
{
    private const UPPY_UPLOAD_CLASS = 'SpykApp\\UppyUpload\\Forms\\Components\\UppyUpload';

    public function dispatch(string $absolutePath, int $userId, int|string|null $tenantId = null, bool $sync = false): void
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Stored import file not found: '.$absolutePath);
        }

        if ($sync) {
            ImportCommonCartridgeJob::dispatchSync($absolutePath, $userId, $tenantId);
        } else {
            ImportCommonCartridgeJob::dispatch($absolutePath, $userId, $tenantId);
        }
    }

    /**
     * Whether SCORM import should use Uppy chunked uploads.
     */
    public static function usesMultipartUpload(): bool
    {
        return (bool) config('filament-lms.common_cartridge_import.multipart_upload.enabled', false)
            && class_exists(self::UPPY_UPLOAD_CLASS);
    }

    /**
     * Stage an uploaded SCORM ZIP and return its absolute filesystem path.
     *
     * Always stores under a UUID filename so concurrent imports of identically
     * named packages cannot overwrite each other before the queued job runs.
     *
     * With multipart upload enabled, $file is a relative storage path (or array
     * of paths) from Uppy and is moved to a unique name. Otherwise $file is a
     * Filament/Livewire temporary upload that is stored as a unique ZIP.
     */
    public function stageUploadedCartridge(mixed $file): ?string
    {
        if (self::usesMultipartUpload()) {
            return $this->stageMultipartCartridge($file);
        }

        $uploadedFile = $this->resolveUploadedFile($file);

        if ($uploadedFile === null) {
            return null;
        }

        $disk = $this->storageDisk();
        $directory = $this->storageDirectory();
        $storedPath = $uploadedFile->storeAs(
            $directory,
            Str::uuid().'.zip',
            $disk
        );

        if ($storedPath === false) {
            return null;
        }

        return Storage::disk($disk)->path($storedPath);
    }

    /**
     * Move an Uppy-uploaded ZIP to a UUID filename under the staging directory.
     *
     * Only accepts disk-relative paths that resolve under the configured staging
     * directory — never arbitrary filesystem paths from form state.
     */
    public function stageMultipartCartridge(mixed $file): ?string
    {
        $sourceRelativePath = $this->validatedStagingRelativePath($file);

        if ($sourceRelativePath === null) {
            return null;
        }

        $storage = Storage::disk($this->storageDisk());
        $uniqueRelativePath = rtrim($this->storageDirectory(), '/').'/'.Str::uuid().'.zip';

        if ($sourceRelativePath === $uniqueRelativePath) {
            return $storage->path($sourceRelativePath);
        }

        if (! $storage->move($sourceRelativePath, $uniqueRelativePath)) {
            return null;
        }

        return $storage->path($uniqueRelativePath);
    }

    /**
     * Resolve a relative storage path (from Uppy) to an absolute filesystem path.
     *
     * Rejects absolute paths and anything that does not resolve under the
     * configured SCORM staging disk/directory.
     */
    public function absolutePathFromStoredRelativePath(mixed $file): ?string
    {
        $relativePath = $this->validatedStagingRelativePath($file);

        if ($relativePath === null) {
            return null;
        }

        return Storage::disk($this->storageDisk())->path($relativePath);
    }

    /**
     * Normalize Filament FileUpload state into an UploadedFile instance.
     */
    public function resolveUploadedFile(mixed $file): ?UploadedFile
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (is_array($file)) {
            $first = Arr::first($file);

            return $this->resolveUploadedFile($first);
        }

        if (is_string($file) && $file !== '') {
            if (is_file($file)) {
                return new UploadedFile($file, basename($file), test: true);
            }

            if (class_exists(TemporaryUploadedFile::class)) {
                try {
                    $temporaryFile = TemporaryUploadedFile::createFromLivewire($file);

                    if ($temporaryFile instanceof UploadedFile) {
                        return $temporaryFile;
                    }
                } catch (Throwable) {
                    // Not a Livewire temporary upload reference.
                }
            }
        }

        return null;
    }

    public function storageDisk(): string
    {
        return (string) config('filament-lms.common_cartridge_import.storage_disk', 'local');
    }

    public function storageDirectory(): string
    {
        return (string) config('filament-lms.common_cartridge_import.storage_directory', 'filament-lms/cartridge-imports');
    }

    /**
     * Return a disk-relative path only when it exists under the SCORM staging directory.
     */
    public function validatedStagingRelativePath(mixed $file): ?string
    {
        if (is_array($file)) {
            $file = Arr::first($file);
        }

        if (! is_string($file) || $file === '') {
            return null;
        }

        if ($this->isAbsolutePath($file) || str_contains($file, "\0")) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $file), '/');

        if ($normalized === '') {
            return null;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        $directory = trim(str_replace('\\', '/', $this->storageDirectory()), '/');

        if ($directory === '' || ! str_starts_with($normalized, $directory.'/')) {
            return null;
        }

        $storage = Storage::disk($this->storageDisk());

        if (! $storage->exists($normalized)) {
            return null;
        }

        $absolutePath = $storage->path($normalized);
        $stagingRoot = $storage->path($directory);

        $realFile = realpath($absolutePath);
        $realRoot = realpath($stagingRoot);

        if ($realFile === false || $realRoot === false || ! is_file($realFile)) {
            return null;
        }

        $realRootPrefix = rtrim($realRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($realFile, $realRootPrefix)) {
            return null;
        }

        return $normalized;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
