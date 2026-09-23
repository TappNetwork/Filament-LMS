<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Livewire\Livewire;
use Tapp\FilamentLms\Livewire\ImageStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

class ImageStepWithErrorBag extends ImageStep
{
    public function getErrorBag(): MessageBagContract
    {
        return new MessageBag;
    }
}

beforeEach(function () {
    if (! Schema::hasColumn('lms_images', 'deleted_at')) {
        Schema::table('lms_images', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
});

test('image step wraps the image in the material container', function () {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'image-step@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $course = Course::factory()->create();
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);

    $image = Image::query()->create([
        'name' => 'Landscape',
        'file_path' => 'images/landscape.png',
    ]);

    $path = sys_get_temp_dir().'/lms-landscape-test.png';
    $png = imagecreatetruecolor(16, 9);
    imagepng($png, $path);

    $image->addMedia($path)->preservingOriginal()->toMediaCollection('image');

    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'name' => 'Landscape Image',
        'slug' => 'landscape-image',
        'material_type' => 'image',
        'material_id' => $image->id,
    ]);

    $html = Livewire::test(ImageStepWithErrorBag::class, ['step' => $step->fresh(['material'])])->html();

    expect($html)
        ->toContain('class="step-material-container"')
        ->toContain('<img')
        ->not->toContain('class="step-material-container rounded-lg');
});
