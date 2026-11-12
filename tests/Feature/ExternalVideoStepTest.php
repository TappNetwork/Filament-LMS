<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Filament\Notifications\Notification;
use Filament\Notifications\NotificationsServiceProvider;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentLms\Livewire\VideoStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Tests\TestUser;

function openExternalVideo(Step $step): VideoStep
{
    $component = new VideoStep;
    $component->mount($step);
    $component->markExternalOpened();

    return $component;
}

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $this->user = TestUser::create([
        'name' => 'Learner',
        'email' => 'learner@example.com',
        'password' => bcrypt('password'),
    ]);
    Auth::login($this->user);

    $this->course = Course::factory()->create(['slug' => 'housing']);
    $lesson = Lesson::factory()->create(['course_id' => $this->course->id]);
    $this->videoStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'name' => 'Recorded webinar',
        'material_type' => 'video',
        'material_id' => Video::factory()->create(['url' => 'https://example.zoom.us/rec/share/abc'])->id,
    ]);
    $this->surveyStep = Step::factory()->create(['lesson_id' => $lesson->id, 'order' => 2]);
});

test('opening an external video persists the step as completed', function () {
    expect($this->videoStep->material->provider)->toBe('external');

    $component = openExternalVideo($this->videoStep);

    expect($component->videoCompleted)->toBeTrue();

    expect($this->videoStep->fresh()->completed_at)->not->toBeNull();
});

test('opening the external video after finishing the later steps completes the course', function () {
    $this->surveyStep->complete($this->user);

    expect($this->course->getCompletionPercentageForUser($this->user->id))->toEqual(50)
        ->and($this->course->completedByUserAt($this->user->id))->toBeNull();

    openExternalVideo($this->videoStep);

    expect($this->course->getCompletionPercentageForUser($this->user->id))->toEqual(100)
        ->and($this->course->completedByUserAt($this->user->id))->not->toBeNull();
});

test('course completed page tells the learner which step blocks the certificate', function () {
    $this->surveyStep->complete($this->user);

    app()->register(NotificationsServiceProvider::class);

    $response = (new CourseCompleted)->mount($this->course->slug);

    expect($response->getTargetUrl())->toContain($this->videoStep->slug);

    Notification::assertNotified(
        Notification::make()
            ->warning()
            ->title('Finish every step to unlock your certificate')
            ->body('"Recorded webinar" isn\'t complete yet.'),
    );
});
