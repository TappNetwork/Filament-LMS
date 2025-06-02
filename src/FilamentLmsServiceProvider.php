<?php

namespace Tapp\FilamentLms;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\FilamentLms\Livewire\DocumentStep;
use Tapp\FilamentLms\Livewire\FormStep;
use Tapp\FilamentLms\Livewire\LinkStep;
use Tapp\FilamentLms\Livewire\VideoPlayer;
use Tapp\FilamentLms\Livewire\VideoStep;
use Tapp\FilamentLms\Livewire\VimeoVideo;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Policies\CertificatePolicy;

class FilamentLmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-lms')
            // TODO how do we get the views working without making them publishable?
            ->hasViews()
            ->hasAssets()
            ->hasConfigFile('filament-lms')
            ->hasMigrations([
                'create_lms_documents_table',
                'create_lms_links_table',
                'create_lms_courses_table',
                'create_lms_lessons_table',
                'create_lms_steps_table',
                'create_lms_step_user_table',
                'create_lms_videos_table',
                'add_text_to_lms_steps_table',
                'create_lms_resources_table',
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
        Livewire::component('document-step', DocumentStep::class);
        Livewire::component('link-step', LinkStep::class);
        Livewire::component('form-step', FormStep::class);
        Livewire::component('vimeo-video', VimeoVideo::class);
        Livewire::component('video-player', VideoPlayer::class);

        FilamentAsset::register([
            Css::make('filament-lms', __DIR__.'/../dist/filament-lms.css'),
            Js::make('filament-lms', __DIR__.'/../dist/filament-lms.js'),
        ], package: 'tapp/filament-lms');

        Relation::morphMap([
            'video' => 'Tapp\FilamentLms\Models\Video',
            'document' => 'Tapp\FilamentLms\Models\Document',
            'link' => 'Tapp\FilamentLms\Models\Link',
            'form' => 'Tapp\FilamentFormBuilder\Models\FilamentForm',
        ]);

        Gate::policy(Course::class, CertificatePolicy::class);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
