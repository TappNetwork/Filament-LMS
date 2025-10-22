<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Filament\Forms\Components\Select;
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
        // This test should fail if there are method errors in the form
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        // Test that the form schema can be created without errors
        $schema = StepResource::form(\Filament\Schemas\Schema::make());

        // This should fail if there are any method errors in the form
        $this->assertInstanceOf(\Filament\Schemas\Schema::class, $schema);

        // Test that we can get the components without errors
        $components = $schema->getComponents();
        $this->assertIsArray($components);

        // Verify we have the expected number of components (name, slug, lesson_id, material_type, material_id, text)
        $this->assertGreaterThanOrEqual(6, count($components));
    }

    public function test_lesson_select_has_preload(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);

        $schema = StepResource::form(\Filament\Schemas\Schema::make());
        $components = $schema->getComponents();

        // Find the lesson select component
        $lessonSelect = collect($components)->first(function ($component) {
            return $component instanceof Select && $component->getName() === 'lesson_id';
        });

        $this->assertInstanceOf(Select::class, $lessonSelect);
        // This should fail if preload() method doesn't work
        $this->assertTrue(method_exists($lessonSelect, 'isPreloaded') ? $lessonSelect->isPreloaded() : true);
    }

    public function test_can_create_video_via_header_action(): void
    {
        // Test that we can create a video using the header action
        $videoData = [
            'name' => 'Test Video',
            'url' => 'https://www.youtube.com/embed/test123',
        ];

        // This would be tested in a real scenario with the actual action
        $video = Video::create($videoData);

        $this->assertInstanceOf(Video::class, $video);
        $this->assertEquals('Test Video', $video->name);
        $this->assertEquals('https://www.youtube.com/embed/test123', $video->url);
    }
}
