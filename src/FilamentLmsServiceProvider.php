<?php

namespace Tapp\FilamentLms;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentLmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-lms')
            ->hasMigrations([
                'create_lms_awards_table',
                'create_lms_courses_table',
                'create_lms_lessons_table',
                'create_lms_steps_table',
                'create_lms_step_user_table',
                'create_lms_videos_table',
            ])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }
}
