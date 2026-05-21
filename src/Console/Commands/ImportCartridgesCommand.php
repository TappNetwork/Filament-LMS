<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;
use Tapp\FilamentLms\Services\CommonCartridge\CommonCartridgeImportService;

final class ImportCartridgesCommand extends Command
{
    protected $signature = 'filament-lms:import-cartridges
                            {--path= : Directory containing ZIP training packages}
                            {--file= : Import a single ZIP file (basename or absolute path)}
                            {--user-id= : User ID for import notifications}
                            {--tenant-id= : Optional tenant ID when tenancy is enabled}
                            {--sync : Run imports synchronously instead of queuing}';

    protected $description = 'Import SCORM / Common Cartridge ZIP packages into LMS courses';

    public function handle(): int
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            $this->error('Could not resolve a user ID. Pass --user-id= or configure filament-lms.user_model.');

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant-id');
        $files = $this->resolveZipFiles();
        if ($files === []) {
            $this->error('No ZIP files found to import.');

            return self::FAILURE;
        }

        $this->info('Importing '.count($files).' package(s)...');
        $this->line('Manual follow-up items:');
        foreach (CommonCartridgeImportService::manualImportGaps() as $gap) {
            $this->line(' - '.$gap);
        }

        foreach ($files as $absolutePath) {
            $this->line('Processing: '.basename($absolutePath));
            if ($this->option('sync')) {
                ImportCommonCartridgeJob::dispatchSync($absolutePath, $userId, $tenantId);
            } else {
                ImportCommonCartridgeJob::dispatch($absolutePath, $userId, $tenantId);
            }
        }

        if ($this->option('sync')) {
            $this->info('Import completed synchronously.');
        } else {
            $this->info('Import job(s) queued. Ensure a queue worker is running.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveZipFiles(): array
    {
        $fileOption = $this->option('file');
        if (is_string($fileOption) && $fileOption !== '') {
            if (is_file($fileOption)) {
                return [$fileOption];
            }

            $directory = $this->resolveImportDirectory();
            $candidate = $directory.'/'.basename($fileOption);
            if (is_file($candidate)) {
                return [$candidate];
            }

            return [];
        }

        $directory = $this->resolveImportDirectory();
        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => mb_strtolower($file->getExtension()) === 'zip')
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();
    }

    private function resolveImportDirectory(): string
    {
        $path = $this->option('path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        $configured = config('filament-lms.common_cartridge_import.default_import_path');

        return is_string($configured) && $configured !== ''
            ? $configured
            : database_path('trainings');
    }

    private function resolveUserId(): ?int
    {
        $userId = $this->option('user-id');
        if (is_numeric($userId)) {
            return (int) $userId;
        }

        $userModel = config('filament-lms.user_model');
        if (! $userModel) {
            return null;
        }

        $user = $userModel::query()->first();

        return $user?->getKey();
    }
}
