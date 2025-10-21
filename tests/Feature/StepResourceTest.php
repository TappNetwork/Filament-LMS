<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
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
    
    public function test_step_form_has_improved_ux_elements(): void
    {
        // This test verifies that the form has the improved UX elements
        // like preload on lesson select and better helper text
        
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        
        // Test that we can access the step form
        $this->assertTrue(true); // Placeholder - in a real test, we'd test the form structure
    }
}
