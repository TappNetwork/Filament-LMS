<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tapp\FilamentLms\Services\MigrateAwardsToCertificateTemplates;
use Tapp\FilamentLms\Support\CertificateBuilder;

final class UpgradeAwardsCommand extends Command
{
    protected $signature = 'filament-lms:upgrade-awards
                            {--award= : Only migrate this award key}
                            {--dry-run : Show the plan without writing}
                            {--force : Recreate layouts for migrated template names}
                            {--dump= : Write a JSON summary to this path}';

    protected $description = 'Convert LMS award Blades to certificate templates and assign every course a template';

    public function handle(MigrateAwardsToCertificateTemplates $migrator): int
    {
        if (! CertificateBuilder::enabled()) {
            $this->error('Certificate builder is not installed. Require tapp/filament-certificate-builder before upgrading.');

            return 1;
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN - no changes will be written.');
        }

        $award = $this->option('award');

        try {
            $summary = $migrator->handle(
                award: is_string($award) && $award !== '' ? $award : null,
                dryRun: (bool) $this->option('dry-run'),
                force: (bool) $this->option('force'),
            );
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->table(
            ['Award', 'Template', 'Created', 'Courses', 'Logos'],
            array_map(
                fn (array $row): array => [
                    $row['award'],
                    $row['template'],
                    $row['created'] ? 'yes' : 'no',
                    $row['courses'],
                    $row['logos'],
                ],
                $summary['awards'],
            ),
        );

        $payload = [
            'templates_created' => $summary['templates_created'],
            'templates_reused' => $summary['templates_reused'],
            'courses_updated' => $summary['courses_updated'],
            'logos_attached' => $summary['logos_attached'],
            'awards' => $summary['awards'],
        ];

        $this->info('Upgrade summary: '.json_encode($payload, JSON_THROW_ON_ERROR));

        $dump = $this->option('dump');

        if (is_string($dump) && $dump !== '') {
            File::ensureDirectoryExists(dirname($dump));
            File::put($dump, json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            $this->info('Wrote '.$dump);
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $unverified = $migrator->unverifiedCourseIds();

        if ($unverified !== []) {
            $this->error('Courses still missing a certificate template: '.implode(', ', $unverified));

            return 2;
        }

        return self::SUCCESS;
    }
}
