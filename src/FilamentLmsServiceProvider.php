<?php

namespace Tapp\FilamentLms;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Livewire;
use Tapp\FilamentLms\Livewire\VideoStep;
use Tapp\FilamentLms\Livewire\FormStep;
use Tapp\FilamentLms\Livewire\VimeoVideo;

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
        Livewire::component('video-step', VideoStep::class);
        Livewire::component('form-step', FormStep::class);
        Livewire::component('vimeo-video', VimeoVideo::class);

        FilamentAsset::register([
            Css::make('filament-lms', __DIR__ . '/../dist/filament-lms.css'),
            Js::make('vimeo', __DIR__ . '/../dist/vimeo.js'),
        ], package: 'tapp/filament-lms');

        Relation::morphMap([
            'video' => 'Tapp\FilamentLms\Models\Video',
            'form' => 'Tapp\FilamentFormBuilder\Models\FilamentForm',
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
