<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\StepResource;
use Tapp\FilamentLms\Tests\TestCase;

class StepResourceTest extends TestCase
{
    public function test_can_create_step_with_video_material(): void
    {
        // Create a course and lesson
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        // Create a video
        $video = Video::factory()->create();

        // Create a step with the video as material
        $step = Step::factory()->create([
            'lesson_id' => $lesson->id,
            'material_id' => $video->id,
            'material_type' => Video::class,
        ]);

        $this->assertInstanceOf(Video::class, $step->material);
        $this->assertEquals($video->id, $step->material->id);
    }

    public function test_step_form_can_be_rendered_without_errors(): void
    {
        $schema = StepResource::form(\Filament\Schemas\Schema::make());
        $this->assertInstanceOf(\Filament\Schemas\Schema::class, $schema);
    }

    public function test_lesson_select_has_preload(): void
    {
        $this->markTestSkipped('Requires Livewire context to resolve form components.');
    }

    public function test_can_create_video(): void
    {
        // Test that we can create a video
        $videoData = [
            'name' => 'Test Video',
            'url' => 'https://www.youtube.com/embed/test123',
        ];

        $video = Video::create($videoData);

        $this->assertInstanceOf(Video::class, $video);
        $this->assertEquals('Test Video', $video->name);
        $this->assertEquals('https://www.youtube.com/embed/test123', $video->url);
    }
}
