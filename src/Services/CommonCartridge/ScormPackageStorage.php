<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class ScormPackageStorage
{
    /**
     * @return array{disk: string, path: string}|null
     */
    public function retainPackage(string $extractedPath): ?array
    {
        if (! config('filament-lms.common_cartridge_import.retain_extracted_packages', true)) {
            return null;
        }

        $disk = (string) config('filament-lms.common_cartridge_import.storage_disk', 'local');
        $packagesDirectory = (string) config('filament-lms.common_cartridge_import.packages_directory', 'lms-scorm-packages');
        $packageId = Str::uuid()->toString();
        $relativePath = mb_rtrim($packagesDirectory, '/').'/'.$packageId;

        $destination = Storage::disk($disk)->path($relativePath);
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }

        if (! File::copyDirectory(mb_rtrim($extractedPath, '/'), $destination)) {
            throw new RuntimeException('Could not copy SCORM package to storage.');
        }

        return [
            'disk' => $disk,
            'path' => $relativePath,
        ];
    }
}
