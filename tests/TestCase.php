<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Tapp\FilamentFormBuilder\FilamentFormBuilderServiceProvider;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentLms\FilamentLmsServiceProvider;
use Tapp\FilamentLms\LmsPanelProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set the current panel for testing
        Filament::setCurrentPanel('lms');

        // Initialize session
        if (! $this->app->bound('session.store')) {
            $this->startSession();
        }

        // Ensure ViewErrorBag is available
        if ($this->app->bound('view')) {
            $errorBag = new ViewErrorBag;
            $errorBag->put('default', new MessageBag);
            $this->app['view']->share('errors', $errorBag);
        }
    }

    final public function getEnvironmentSetUp($app)
    {
        // Set up the database connection for testing
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up filesystem for testing
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);

        // Set up session configuration
        $app['config']->set('session.driver', 'array');

        // Set up view error bag sharing
        $app['config']->set('view.compiled', storage_path('framework/views'));

        // Set up media library configuration
        $app['config']->set('media-library.disk_name', 'local');
        $app['config']->set('media-library.media_model', Media::class);

        // Set up app key for testing
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Set up filament-lms user model for testing
        $app['config']->set('filament-lms.user_model', TestUser::class);

        // Set up database tables
        $this->setUpDatabase($app);
    }

    protected function setUpDatabase($app)
    {
        // Create users table (first_name/last_name for course progress reporting query)
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Create lms_courses table
        $app['db']->connection()->getSchemaBuilder()->create('lms_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('external_id')->unique();
            $table->text('image')->nullable();
            $table->string('award')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('required_test_percentage')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('embedded_player')->default(false);
            $table->string('completion_mode', 32)->default('native');
            $table->foreignId('evaluation_course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create lms_lessons table
        $app['db']->connection()->getSchemaBuilder()->create('lms_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->references('id')->on('lms_courses')->onDelete('cascade');
            $table->unsignedInteger('order');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->softDeletes();
        });

        // Create lms_steps table (material nullable to match make_material_nullable migration)
        $app['db']->connection()->getSchemaBuilder()->create('lms_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->references('id')->on('lms_lessons')->onDelete('cascade');
            $table->unsignedInteger('order');
            $table->boolean('is_optional')->default(false);
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('material_id')->nullable();
            $table->string('material_type')->nullable();
            $table->text('text')->nullable();
            $table->string('player_slide_id')->nullable();
            $table->foreignId('retry_step_id')->nullable()->constrained('lms_steps')->onDelete('set null');
            $table->boolean('require_perfect_score')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Create lms_step_user table
        $app['db']->connection()->getSchemaBuilder()->create('lms_step_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->references('id')->on('lms_steps')->onDelete('cascade');
            $table->foreignId('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignId('evaluation_primary_course_id')->nullable()->constrained('lms_courses')->nullOnDelete();
            $table->foreignId('filament_form_user_id')->nullable()->constrained('filament_form_user')->nullOnDelete();
            $table->unsignedInteger('seconds')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Create lms_course_user table
        $app['db']->connection()->getSchemaBuilder()->create('lms_course_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Create lms_videos table
        $app['db']->connection()->getSchemaBuilder()->create('lms_videos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create lms_documents table
        $app['db']->connection()->getSchemaBuilder()->create('lms_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path')->nullable();
            $table->string('package_disk')->nullable();
            $table->string('package_path')->nullable();
            $table->string('package_launch_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create lms_links table
        $app['db']->connection()->getSchemaBuilder()->create('lms_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create lms_tests table
        $app['db']->connection()->getSchemaBuilder()->create('lms_tests', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('filament_form_id')->nullable();
            $table->unsignedBigInteger('filament_form_user_id')->nullable();
            $table->timestamps();
        });

        // Create lms_images table
        $app['db']->connection()->getSchemaBuilder()->create('lms_images', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create media table for Spatie Media Library
        $app['db']->connection()->getSchemaBuilder()->create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->nullableTimestamps();
        });

        if (class_exists(FilamentForm::class)) {
            $app['db']->connection()->getSchemaBuilder()->create('filament_forms', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->text('redirect_url')->nullable();
                $table->boolean('permit_guest_entries')->default(false);
                $table->boolean('locked')->default(false);
            });

            $app['db']->connection()->getSchemaBuilder()->create('filament_form_fields', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->foreignId('filament_form_id')->constrained('filament_forms')->cascadeOnDelete();
                $table->integer('order');
                $table->string('field')->nullable();
                $table->string('type');
                $table->string('label');
                $table->string('hint')->nullable();
                $table->boolean('required')->default(false);
                $table->json('rules')->nullable();
                $table->json('options')->nullable();
                $table->json('schema')->nullable();
            });

            $app['db']->connection()->getSchemaBuilder()->create('filament_form_user', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
                $table->foreignId('filament_form_id')->constrained('filament_forms')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->json('entry');
            });
        }
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            ExcelServiceProvider::class,
            SupportServiceProvider::class,
            MediaLibraryServiceProvider::class,
            FilamentLmsServiceProvider::class,
            LmsPanelProvider::class,
        ];

        // Only add FilamentFormBuilderServiceProvider if it exists
        if (class_exists(FilamentFormBuilderServiceProvider::class)) {
            $providers[] = FilamentFormBuilderServiceProvider::class;
        }

        if (class_exists(McpServiceProvider::class)) {
            $providers[] = McpServiceProvider::class;
        }

        return $providers;
    }
}
