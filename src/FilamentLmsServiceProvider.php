<?php

namespace Tapp\FilamentLms;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
                use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

class FilamentLmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-lms')
            ->hasViews()
            ->hasAssets()
            ->hasMigrations([
                'create_lms_courses_table',
                'create_lms_lessons_table',
                'create_lms_steps_table',
                'create_lms_step_user_table',
                'create_lms_videos_table',
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted()
    {
        FilamentAsset::register([
            Css::make('filament-lms', __DIR__ . '/../resources/dist/filament-lms.css'),
        ], package: 'tapp/filament-lms');
    }
}
