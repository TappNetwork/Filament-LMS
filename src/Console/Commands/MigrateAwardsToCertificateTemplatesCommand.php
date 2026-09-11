<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;

final class MigrateAwardsToCertificateTemplatesCommand extends Command
{
    protected $hidden = true;

    protected $signature = 'filament-lms:migrate-awards-to-templates
                            {--award= : Only migrate this award key}
                            {--dry-run : Show the plan without writing}
                            {--force : Recreate layouts for migrated template names}
                            {--dump= : Write a JSON summary to this path}';

    protected $description = 'Deprecated alias of filament-lms:upgrade-awards';

    public function handle(): int
    {
        $this->warn('filament-lms:migrate-awards-to-templates is deprecated. Use filament-lms:upgrade-awards.');

        return $this->call('filament-lms:upgrade-awards', [
            '--award' => $this->option('award'),
            '--dry-run' => $this->option('dry-run'),
            '--force' => $this->option('force'),
            '--dump' => $this->option('dump'),
        ]);
    }
}
