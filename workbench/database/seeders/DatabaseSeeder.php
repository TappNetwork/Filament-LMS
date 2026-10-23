<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Tests\TestUser;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $user = TestUser::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create course
        $course = Course::factory()->create([
            'name' => 'Introduction to Filament LMS',
            'slug' => 'intro-to-filament-lms',
            'external_id' => 'intro_lms',
            'description' => 'Learn how to use the Filament LMS package',
            'is_private' => false,
        ]);

        // Create lessons
        $lesson1 = Lesson::factory()->create([
            'course_id' => $course->id,
            'name' => 'Getting Started',
            'slug' => 'getting-started',
            'order' => 1,
        ]);

        $lesson2 = Lesson::factory()->create([
            'course_id' => $course->id,
            'name' => 'Advanced Features',
            'slug' => 'advanced-features',
            'order' => 2,
        ]);

        // Create videos
        $video1 = Video::factory()->create([
            'name' => 'Welcome Video',
            'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);

        $video2 = Video::factory()->create([
            'name' => 'Setup Tutorial',
            'url' => 'https://www.youtube.com/embed/jNQXAC9IVRw',
        ]);

        $video3 = Video::factory()->create([
            'name' => 'Admin Panel Guide',
            'url' => 'https://www.youtube.com/embed/9bZkp7q19f0',
        ]);

        // Create steps
        Step::factory()->create([
            'lesson_id' => $lesson1->id,
            'name' => 'Welcome',
            'slug' => 'getting-started-welcome',
            'material_type' => Video::class,
            'material_id' => $video1->id,
            'order' => 1,
        ]);

        Step::factory()->create([
            'lesson_id' => $lesson1->id,
            'name' => 'Installation',
            'slug' => 'getting-started-installation',
            'material_type' => Video::class,
            'material_id' => $video2->id,
            'order' => 2,
        ]);

        Step::factory()->create([
            'lesson_id' => $lesson2->id,
            'name' => 'Admin Panel Overview',
            'slug' => 'advanced-features-admin-panel',
            'material_type' => Video::class,
            'material_id' => $video3->id,
            'order' => 1,
        ]);

        $this->command->info('✓ Seeded: 1 user, 1 course, 2 lessons, 3 steps');
    }
}
