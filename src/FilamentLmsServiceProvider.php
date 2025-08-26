<?php

namespace Tapp\FilamentLms;

use Tapp\FilamentLms\Livewire\ImageStep;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\FilamentLms\Livewire\DocumentStep;
use Tapp\FilamentLms\Livewire\FormStep;
use Tapp\FilamentLms\Livewire\GradedKeyValueEntry;
use Tapp\FilamentLms\Livewire\LinkStep;
use Tapp\FilamentLms\Livewire\TestStep;
use Tapp\FilamentLms\Livewire\VideoPlayer;
use Tapp\FilamentLms\Livewire\VideoStep;
use Tapp\FilamentLms\Livewire\ViewGradedEntry;
use Tapp\FilamentLms\Livewire\VimeoVideo;
use Tapp\FilamentLms\Pages\CreateTestEntry;

class FilamentLmsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-lms')
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
                'create_lms_tests_table',
                'create_lms_course_user_table',
                'create_lms_images_table',
            ])
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted()
    {
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-lms'),
        ], 'filament-lms-views');

        Livewire::component('video-step', VideoStep::class);
        Livewire::component('document-step', DocumentStep::class);
        Livewire::component('link-step', LinkStep::class);
        Livewire::component('form-step', FormStep::class);
        Livewire::component('test-step', TestStep::class);
        Livewire::component('vimeo-video', VimeoVideo::class);
        Livewire::component('video-player', VideoPlayer::class);
        Livewire::component('create-test-entry', CreateTestEntry::class);
        Livewire::component('view-graded-entry', ViewGradedEntry::class);
        Livewire::component('graded-key-value-entry', GradedKeyValueEntry::class);
        Livewire::component('image-step', ImageStep::class);

        FilamentAsset::register([
            Css::make('filament-lms', __DIR__.'/../dist/filament-lms.css'),
            Js::make('filament-lms', __DIR__.'/../dist/filament-lms.js'),
        ], package: 'tapp/filament-lms');

        Relation::morphMap([
            'video' => 'Tapp\FilamentLms\Models\Video',
            'document' => 'Tapp\FilamentLms\Models\Document',
            'link' => 'Tapp\FilamentLms\Models\Link',
            'form' => 'Tapp\FilamentFormBuilder\Models\FilamentForm',
            'test' => 'Tapp\FilamentLms\Models\Test',
            'image' => 'Tapp\FilamentLms\Models\Image',
        ]);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
