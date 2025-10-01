<?php

namespace Tapp\FilamentLms\Tests;

use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Tapp\FilamentFormBuilder\FilamentFormBuilderServiceProvider;
use Tapp\FilamentLms\FilamentLmsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestTables();
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            SupportServiceProvider::class,
            MediaLibraryServiceProvider::class,
            FilamentLmsServiceProvider::class,
            FilamentFormBuilderServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Set up the database connection for testing
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up filesystem for testing
        config()->set('filesystems.default', 'local');
        config()->set('filesystems.disks.local', [
            'driver' => 'local',
            'root' => storage_path('app'),
        ]);

        // Set up media library configuration
        config()->set('media-library.disk_name', 'local');
        config()->set('media-library.media_model', \Spatie\MediaLibrary\MediaCollections\Models\Media::class);

        // Set up app key for testing
        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    protected function createTestTables(): void
    {
        // Create users table
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Create lms_courses table
        if (! Schema::hasTable('lms_courses')) {
            Schema::create('lms_courses', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('external_id')->nullable();
                $table->string('award')->default('default');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_lessons table
        if (! Schema::hasTable('lms_lessons')) {
            Schema::create('lms_lessons', function ($table) {
                $table->id();
                $table->foreignId('course_id')->constrained('lms_courses')->onDelete('cascade');
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->integer('order')->default(0);
                $table->timestamps();
            });
        }

        // Create lms_steps table
        if (! Schema::hasTable('lms_steps')) {
            Schema::create('lms_steps', function ($table) {
                $table->id();
                $table->foreignId('lesson_id')->constrained('lms_lessons')->onDelete('cascade');
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->text('text')->nullable();
                $table->string('material_type')->nullable();
                $table->unsignedBigInteger('material_id')->nullable();
                $table->integer('order')->default(0);
                $table->boolean('last_step')->default(false);
                $table->timestamps();
            });
        }

        // Create lms_step_user table
        if (! Schema::hasTable('lms_step_user')) {
            Schema::create('lms_step_user', function ($table) {
                $table->id();
                $table->foreignId('step_id')->constrained('lms_steps')->onDelete('cascade');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_course_user table
        if (! Schema::hasTable('lms_course_user')) {
            Schema::create('lms_course_user', function ($table) {
                $table->id();
                $table->foreignId('course_id')->constrained('lms_courses')->onDelete('cascade');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_videos table
        if (! Schema::hasTable('lms_videos')) {
            Schema::create('lms_videos', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('url');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_documents table
        if (! Schema::hasTable('lms_documents')) {
            Schema::create('lms_documents', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('file_path');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_links table
        if (! Schema::hasTable('lms_links')) {
            Schema::create('lms_links', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('url');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_tests table
        if (! Schema::hasTable('lms_tests')) {
            Schema::create('lms_tests', function ($table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('filament_form_id')->nullable();
                $table->unsignedBigInteger('filament_form_user_id')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_resources table
        if (! Schema::hasTable('lms_resources')) {
            Schema::create('lms_resources', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create lms_images table
        if (! Schema::hasTable('lms_images')) {
            Schema::create('lms_images', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('file_path');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create media table for Spatie Media Library
        if (! Schema::hasTable('media')) {
            Schema::create('media', function ($table) {
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
        }
    }
}
