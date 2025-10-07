<?php

namespace Tapp\FilamentLms\Tests;

use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
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
    }
}
