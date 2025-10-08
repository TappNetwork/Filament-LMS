<?php

namespace Tapp\FilamentLms\Tests;

use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
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
    }

    protected function setUpDatabase($app)
    {
        // Create users table
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Create lms_courses table
        $app['db']->connection()->getSchemaBuilder()->create('lms_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('external_id')->nullable();
            $table->string('award')->default('default');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Create lms_lessons table
        $app['db']->connection()->getSchemaBuilder()->create('lms_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('lms_courses')->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Create lms_steps table
        $app['db']->connection()->getSchemaBuilder()->create('lms_steps', function (Blueprint $table) {
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

        // Create lms_step_user table
        $app['db']->connection()->getSchemaBuilder()->create('lms_step_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('lms_steps')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
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
            $table->string('file_path');
            $table->text('description')->nullable();
            $table->timestamps();
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
            $table->string('name');
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

        // Set up media library configuration
        $app['config']->set('media-library.disk_name', 'local');
        $app['config']->set('media-library.media_model', \Spatie\MediaLibrary\MediaCollections\Models\Media::class);

        // Set up app key for testing
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Set up database tables
        $this->setUpDatabase($app);
    }
}
