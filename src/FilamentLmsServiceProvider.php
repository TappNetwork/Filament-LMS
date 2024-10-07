<?php

namespace Tapp\FilamentLms;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentLmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-lms')
            ->hasViews();
            // ->hasConfigFile()
            // ->hasMigration('create_filament-lms_table')
            // ->hasCommand(InstallFilamentLms::class)
    }
}
