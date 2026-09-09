<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Tapp\FilamentLms\Services\MigrateAwardsToCertificateTemplates;
use Tapp\FilamentLms\Support\CertificateBuilder;

final class MigrateAwardsToCertificateTemplatesCommand extends Command
{
    protected $signature = 'filament-lms:migrate-awards-to-templates
                            {--award= : Only migrate this award key}
                            {--dry-run : Show the plan without writing}
                            {--force : Recreate layouts and reassign courses that already have a template}';

    protected $description = 'Create certificate templates from LMS award Blade views and assign them to courses';

    public function handle(MigrateAwardsToCertificateTemplates $migrator): int
    {
        if (! CertificateBuilder::enabled()) {
            $this->error('Certificate builder is not enabled. Install tapp/filament-certificate-builder and set filament-lms.integrations.certificate_builder.enabled to true.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN - no changes will be written.');
        }

        $award = $this->option('award');

        $summary = $migrator->handle(
            award: is_string($award) && $award !== '' ? $award : null,
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
        );

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

        $this->info('Migration summary: '.json_encode([
            'templates_created' => $summary['templates_created'],
            'templates_reused' => $summary['templates_reused'],
            'courses_updated' => $summary['courses_updated'],
            'logos_attached' => $summary['logos_attached'],
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
