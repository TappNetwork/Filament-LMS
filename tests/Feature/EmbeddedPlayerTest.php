<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Events\CourseStarted;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Services\ScormProgressService;
use Tapp\FilamentLms\Tests\TestUser;

test('embedded course launch step is the document step with scorm package', function () {
    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    $launchStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'name' => 'Later',
    ]);

    expect($course->launchStep()?->is($launchStep))->toBeTrue();
});

test('embedded course launch step fallback respects lesson and step order', function () {
    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
    ]);

    $firstLesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $secondLesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 2]);

    Step::factory()->create([
        'lesson_id' => $secondLesson->id,
        'order' => 1,
        'name' => 'First in lesson two',
    ]);
    $expectedLaunchStep = Step::factory()->create([
        'lesson_id' => $firstLesson->id,
        'order' => 1,
        'name' => 'First in lesson one',
    ]);
    $laterStepInFirstLesson = Step::factory()->create([
        'lesson_id' => $firstLesson->id,
        'order' => 2,
        'name' => 'Later in lesson one',
    ]);

    $launch = $course->fresh()->launchStep();

    expect($launch?->id)->toBe($expectedLaunchStep->id)
        ->and($launch?->is($laterStepInFirstLesson))->toBeFalse();
});

test('scorm commit marks step by player slide id and bulk completes on passed status', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-commit@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $slideId = 'Slide_abc123';
    $stepWithSlide = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'player_slide_id' => $slideId,
        'material_type' => null,
        'material_id' => null,
    ]);
    $otherStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => null,
        'material_id' => null,
    ]);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_location' => $slideId,
        'lesson_status' => 'incomplete',
    ])->assertSuccessful();

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $stepWithSlide->id)
        ->whereNotNull('completed_at')
        ->exists())->toBeTrue();

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_status' => 'passed',
    ])->assertSuccessful();

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $otherStep->id)
        ->whereNotNull('completed_at')
        ->exists())->toBeTrue();
});

test('scorm commit marks step by articulate storyline bookmark format', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-storyline-bookmark@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $presentationStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'player_slide_id' => '6MJxGqrUPQe',
        'material_type' => null,
        'material_id' => null,
    ]);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_location' => '_player.6MZDyRg6qdt.6MJxGqrUPQe',
        'lesson_status' => 'incomplete',
    ])->assertSuccessful();

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $presentationStep->id)
        ->whereNotNull('completed_at')
        ->exists())->toBeTrue();
});

test('scorm commit completes prior storyline steps when a later slide is reached', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-storyline-cascade@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $firstStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'player_slide_id' => 'first-slide',
        'material_type' => null,
        'material_id' => null,
    ]);
    $secondStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'player_slide_id' => 'second-slide',
        'material_type' => null,
        'material_id' => null,
    ]);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_location' => '_player.session.second-slide',
        'lesson_status' => 'incomplete',
    ])->assertSuccessful();

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->whereIn('step_id', [$firstStep->id, $secondStep->id])
        ->whereNotNull('completed_at')
        ->count())->toBe(2);
});

test('scorm commit marks furthest step from storyline load tracker style suspend data', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-storyline-load-tracker@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $firstStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'player_slide_id' => '5yZFhFvQaCN',
        'material_type' => null,
        'material_id' => null,
    ]);
    $secondStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'player_slide_id' => '6MJxGqrUPQe',
        'material_type' => null,
        'material_id' => null,
    ]);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'suspend_data' => '_player.session1.5yZFhFvQaCN|_player.session1.6MJxGqrUPQe',
        'lesson_status' => 'incomplete',
    ])->assertSuccessful();

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->whereIn('step_id', [$firstStep->id, $secondStep->id])
        ->whereNotNull('completed_at')
        ->count())->toBe(2);
});

test('html5 embedded player completion percentage uses step count not lesson count', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'html5-step-progress@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $firstStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => null,
        'material_id' => null,
    ]);
    $lastStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => null,
        'material_id' => null,
    ]);

    StepUser::query()->create([
        'user_id' => $user->id,
        'step_id' => $firstStep->id,
        'completed_at' => now(),
    ]);

    expect((float) $course->fresh()->getCompletionPercentageForUser($user->id))->toBe(50.0);

    StepUser::query()->create([
        'user_id' => $user->id,
        'step_id' => $lastStep->id,
        'completed_at' => now(),
    ]);

    expect((float) $course->fresh()->getCompletionPercentageForUser($user->id))->toBe(100.0);
});

test('embedded player completion percentage is based on completed modules', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'embedded-module-progress@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
    ]);
    $firstLesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 1]);
    $secondLesson = Lesson::factory()->create(['course_id' => $course->id, 'order' => 2]);
    $firstStep = Step::factory()->create([
        'lesson_id' => $firstLesson->id,
        'order' => 0,
        'player_slide_id' => 'module-one',
        'material_type' => null,
        'material_id' => null,
    ]);
    $secondStep = Step::factory()->create([
        'lesson_id' => $secondLesson->id,
        'order' => 0,
        'player_slide_id' => 'module-two',
        'material_type' => null,
        'material_id' => null,
    ]);

    StepUser::query()->create([
        'user_id' => $user->id,
        'step_id' => $firstStep->id,
        'completed_at' => now(),
    ]);

    expect((float) $course->fresh()->getCompletionPercentageForUser($user->id))->toBe(50.0);

    StepUser::query()->create([
        'user_id' => $user->id,
        'step_id' => $secondStep->id,
        'completed_at' => now(),
    ]);

    expect((float) $course->fresh()->getCompletionPercentageForUser($user->id))->toBe(100.0);
});

test('scorm commit rejects html5 progress flags for scorm 1.2 courses', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-html5-guard@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => null,
        'material_id' => null,
    ]);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'html5_progress' => true,
    ])->assertStatus(422);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'html5_complete' => true,
    ])->assertStatus(422);
});

test('scorm commit sets completed_at on existing enrollment without duplicate key error', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-enrolled@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => null,
        'material_id' => null,
    ]);

    $course->users()->attach($user->id);

    expect($course->completedByUserAt($user->id))->toBeNull();

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_status' => 'completed',
    ])->assertSuccessful();

    expect($course->completedByUserAt($user->id))->not->toBeNull();
});

test('double scorm completion commit is idempotent', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-double-complete@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => null,
        'material_id' => null,
    ]);

    $course->users()->attach($user->id);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_status' => 'completed',
    ])->assertSuccessful();

    $firstCompletedAt = $course->fresh()->completedByUserAt($user->id);
    expect($firstCompletedAt)->not->toBeNull();

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_status' => 'completed',
    ])->assertSuccessful();

    expect($course->fresh()->completedByUserAt($user->id))->toBe($firstCompletedAt);
});

test('scorm commit does not bulk complete test steps', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'scorm-skip-test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $test = Test::query()->create(['name' => 'Quiz']);
    $testStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'test',
        'material_id' => $test->id,
    ]);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'lesson_status' => 'completed',
    ])->assertSuccessful();

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $testStep->id)
        ->whereNotNull('completed_at')
        ->exists())->toBeFalse();
});

test('html5 record started creates launch step progress row', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'html5-start@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    $launchStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    app(ScormProgressService::class)->recordStarted($course, $user);

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $launchStep->id)
        ->exists())->toBeTrue();
});

test('record started dispatches course started for non-first launch step', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    Event::fake([CourseStarted::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'course-started@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Scorm12,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'name' => 'Intro',
        'material_type' => null,
        'material_id' => null,
    ]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    app(ScormProgressService::class)->recordStarted($course, $user);

    Event::assertDispatched(CourseStarted::class, function (CourseStarted $event) use ($course, $user): bool {
        return $event->course->is($course) && $event->user->is($user);
    });
});

test('html5 manual complete is rejected without meaningful progress', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'html5-guard@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
    ]);

    app(ScormProgressService::class)->recordStarted($course, $user);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'html5_complete' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('ok', false);

    expect(app(ScormProgressService::class)->userMayConfirmCourseCompletion($course, $user))->toBeFalse();
});

test('html5 manual complete is allowed after player progress and minimum session', function () {
    config([
        'filament-lms.user_model' => TestUser::class,
        'filament-lms.embedded_player_min_session_seconds_html5' => 1,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'html5-allow@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    $launchStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    $otherStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
    ]);

    $service = app(ScormProgressService::class);
    $service->recordStarted($course, $user);
    $service->markPlayerProgress($course, $user);

    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $launchStep->id)
        ->update(['created_at' => now()->subMinutes(10)]);

    $this->actingAs($user);

    expect($service->userMayConfirmCourseCompletion($course, $user))->toBeTrue();

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'html5_complete' => true,
    ])->assertSuccessful()
        ->assertJsonPath('ok', true);

    expect(StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $otherStep->id)
        ->whereNotNull('completed_at')
        ->exists())->toBeTrue();
});

test('html5 progress commit records player activity', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'html5-progress@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    app(ScormProgressService::class)->recordStarted($course, $user);

    $this->actingAs($user);

    $this->postJson(route('filament-lms.scorm-commit.store', ['course' => $course]), [
        'html5_progress' => true,
    ])->assertSuccessful();

    expect(app(ScormProgressService::class)->hasRecordedPlayerProgress($course, $user))->toBeTrue();
});

test('only launch step is accessible on embedded courses', function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'embedded-access@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create([
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Player',
        'package_disk' => 'local',
        'package_path' => 'lms-scorm-packages/'.Str::uuid(),
        'package_launch_path' => 'index.html',
    ]);
    $launchStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 0,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    $otherStep = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
    ]);

    expect($user->canAccessStep($launchStep))->toBeTrue();
    expect($user->canAccessStep($otherStep))->toBeFalse();
});
