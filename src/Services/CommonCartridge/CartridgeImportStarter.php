<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;
use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;
use Throwable;

final class CartridgeImportStarter
{
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
}
