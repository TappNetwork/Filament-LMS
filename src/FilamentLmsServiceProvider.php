<?php

namespace Tapp\FilamentLms;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\FilamentLibrary\Models\LibraryItem;
use Tapp\FilamentLms\Console\Commands\BackfillCourseCompletedAt;
use Tapp\FilamentLms\Console\Commands\BackfillEmbeddedPlayerCourses;
use Tapp\FilamentLms\Console\Commands\ImportCartridgesCommand;
use Tapp\FilamentLms\Console\Commands\ReconcileUserGroupMemberships;
use Tapp\FilamentLms\Livewire\DocumentStep;
use Tapp\FilamentLms\Livewire\FormStep;
use Tapp\FilamentLms\Livewire\ImageStep;
use Tapp\FilamentLms\Livewire\LibraryFileStep;
use Tapp\FilamentLms\Livewire\LibraryLinkStep;
use Tapp\FilamentLms\Livewire\LinkStep;
use Tapp\FilamentLms\Livewire\LmsTestFormShow;
use Tapp\FilamentLms\Livewire\TestStep;
use Tapp\FilamentLms\Livewire\VideoPlayer;
use Tapp\FilamentLms\Livewire\VideoStep;
use Tapp\FilamentLms\Livewire\ViewGradedEntry;
use Tapp\FilamentLms\Livewire\VimeoVideo;
use Tapp\FilamentLms\Observers\UserGroupMembershipUserObserver;
use Tapp\FilamentLms\Pages\CreateTestEntry;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;

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
                'create_lms_tests_table',
                'create_lms_course_user_table',
                'create_lms_images_table',
                'rename_hidden_to_is_private_in_lms_courses_table',
                'add_is_optional_to_lms_steps_table',
                'add_test_step_features_to_lms_steps_table',
                'add_required_test_percentage_to_lms_courses_table',
                'add_completed_at_to_lms_course_user_table',
                'make_material_nullable_in_lms_steps_table',
                'backfill_lms_course_user_completed_at_from_step_dates',
                'change_name_to_text_on_lms_tests_table',
                'create_lms_credit_categories_table',
                'create_lms_course_credit_category_table',
                'add_scorm_package_columns_to_lms_documents_table',
                'add_embedded_player_to_lms_courses_table',
                'add_player_slide_id_to_lms_steps_table',
                'add_evaluation_course_id_to_lms_courses_table',
                'add_filament_form_user_id_to_lms_step_user_table',
                'add_evaluation_primary_course_id_to_lms_step_user_table',
                'create_lms_user_groups_table',
                'create_lms_course_user_group_table',
                'create_lms_user_group_memberships_table',
                'add_is_explicitly_assigned_to_lms_course_user_table',
            ])
            ->hasCommand(BackfillCourseCompletedAt::class)
            ->hasCommand(BackfillEmbeddedPlayerCourses::class)
            ->hasCommand(ImportCartridgesCommand::class)
            ->hasCommand(ReconcileUserGroupMemberships::class)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted()
    {
        // Load migrations for Orchestra Testbench
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

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
        Livewire::component('lms-test-form-show', LmsTestFormShow::class);
        Livewire::component('view-graded-entry', ViewGradedEntry::class);
        Livewire::component('image-step', ImageStep::class);

        // Register library step components whenever the optional package is present.
        // Config `integrations.filament_library.enabled` only gates admin UI (material picker);
        // morph resolution and learner-facing steps must work for existing DB rows even when disabled.
        if (class_exists(LibraryItem::class)) {
            Livewire::component('library-file-step', LibraryFileStep::class);
            Livewire::component('library-link-step', LibraryLinkStep::class);
        }

        FilamentAsset::register([
            Css::make('filament-lms', __DIR__.'/../dist/filament-lms.css'),
            Js::make('filament-lms', __DIR__.'/../dist/filament-lms.js'),
        ], package: 'tapp/filament-lms');

        $morphMap = [
            'video' => 'Tapp\FilamentLms\Models\Video',
            'document' => 'Tapp\FilamentLms\Models\Document',
            'link' => 'Tapp\FilamentLms\Models\Link',
            'form' => 'Tapp\FilamentFormBuilder\Models\FilamentForm',
            'test' => 'Tapp\FilamentLms\Models\Test',
            'image' => 'Tapp\FilamentLms\Models\Image',
        ];

        if (class_exists(LibraryItem::class)) {
            $morphMap['library_file'] = LibraryItem::class;
            $morphMap['library_link'] = LibraryItem::class;
        }

        Relation::morphMap($morphMap);

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->configureLivewireTemporaryUploadLimits();
        $this->registerUserGroupMembershipObserver();
    }

    protected function registerUserGroupMembershipObserver(): void
    {
        $registry = $this->app->make(UserGroupCriteriaRegistry::class);

        if (! $registry->enabled()) {
            return;
        }

        $userModel = config('filament-lms.user_model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            return;
        }

        $userModel::observe(UserGroupMembershipUserObserver::class);
    }

    protected function configureLivewireTemporaryUploadLimits(): void
    {
        $maxKb = (int) config('filament-lms.common_cartridge_import.max_upload_size_kb', 512000);
        $maxMinutes = (int) config('filament-lms.common_cartridge_import.max_upload_time_minutes', 10);

        /** @var array<int, string>|null $rules */
        $rules = config('livewire.temporary_file_upload.rules');

        if ($rules === null) {
            $rules = ['required', 'file', 'max:12288'];
        }

        $rules = array_values(array_filter(
            $rules,
            fn (string $rule): bool => ! str_starts_with($rule, 'max:'),
        ));
        $rules[] = 'max:'.$maxKb;

        config([
            'livewire.temporary_file_upload.rules' => $rules,
            'livewire.temporary_file_upload.max_upload_time' => $maxMinutes,
        ]);
    }
}
