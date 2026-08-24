<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Laravel\Mcp\Server;
use Tapp\FilamentLms\Mcp\LmsServer;
use Tapp\FilamentLms\Mcp\Tools\CreateLesson;
use Tapp\FilamentLms\Mcp\Tools\CreateVideoCourse;
use Tapp\FilamentLms\Mcp\Tools\CreateVideoStep;
use Tapp\FilamentLms\Mcp\Tools\DeleteCourse;
use Tapp\FilamentLms\Mcp\Tools\DeleteLesson;
use Tapp\FilamentLms\Mcp\Tools\DeleteStep;
use Tapp\FilamentLms\Mcp\Tools\GetCourse;
use Tapp\FilamentLms\Mcp\Tools\ListCourses;
use Tapp\FilamentLms\Mcp\Tools\UpdateCourse;
use Tapp\FilamentLms\Mcp\Tools\UpdateLesson;
use Tapp\FilamentLms\Mcp\Tools\UpdateStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;

beforeEach(function () {
    if (! class_exists(LmsServer::class) || ! class_exists(Server::class)) {
        $this->markTestSkipped('laravel/mcp is required to run LMS MCP tests.');
    }
});

function videoCoursePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'DNS Cloudflare',
        'description' => 'How DNS works with Cloudflare.',
        'lessons' => [
            [
                'name' => 'Getting started',
                'steps' => [
                    [
                        'name' => 'Welcome video',
                        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'text' => 'Welcome to the course.',
                    ],
                ],
            ],
        ],
    ], $overrides);
}

test('create_video_course defaults new courses to private', function () {
    $response = LmsServer::tool(CreateVideoCourse::class, videoCoursePayload());

    $response->assertOk();

    $course = Course::query()->first();
    expect($course)->not->toBeNull()
        ->and($course->is_private)->toBeTrue()
        ->and($course->slug)->toBe('dns-cloudflare')
        ->and($course->external_id)->toBe('dns_cloudflare')
        ->and($course->award)->toBe('default')
        ->and($course->completion_mode->value)->toBe('native');
});

test('create_video_course creates nested lessons, videos, and steps', function () {
    $response = LmsServer::tool(CreateVideoCourse::class, videoCoursePayload());

    $response->assertOk()
        ->assertSee('DNS Cloudflare')
        ->assertSee('Getting started')
        ->assertSee('Welcome video');

    expect(Course::query()->count())->toBe(1)
        ->and(Lesson::query()->count())->toBe(1)
        ->and(Step::query()->count())->toBe(1)
        ->and(Video::query()->count())->toBe(1);

    $step = Step::query()->first();
    expect($step->material_type)->toBe('video')
        ->and($step->text)->toBe('Welcome to the course.')
        ->and($step->is_optional)->toBeFalse();
});

test('create_video_course converts youtube watch urls to embed urls', function () {
    LmsServer::tool(CreateVideoCourse::class, videoCoursePayload())->assertOk();

    $video = Video::query()->first();
    expect($video->url)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
        ->and($video->provider)->toBe('youtube');
});

test('create_video_course rejects invalid video urls', function () {
    $response = LmsServer::tool(CreateVideoCourse::class, videoCoursePayload([
        'lessons' => [
            [
                'name' => 'Getting started',
                'steps' => [
                    [
                        'name' => 'Broken video',
                        'video_url' => 'https://example.com/not-a-video',
                    ],
                ],
            ],
        ],
    ]));

    $response->assertHasErrors(['Automatic conversion from video link to embed link failed']);
    expect(Course::query()->count())->toBe(0);
});

test('create_video_course rejects duplicate slug and external_id', function () {
    Course::factory()->create([
        'name' => 'Existing Course',
        'slug' => 'dns-cloudflare',
        'external_id' => 'dns_cloudflare',
    ]);

    $response = LmsServer::tool(CreateVideoCourse::class, videoCoursePayload());

    $response->assertHasErrors();
    expect(Course::query()->count())->toBe(1);
});

test('create_video_course rejects invalid external_id format', function () {
    $response = LmsServer::tool(CreateVideoCourse::class, videoCoursePayload([
        'external_id' => '123-not-valid',
    ]));

    $response->assertHasErrors(['External ID must contain only lowercase letters']);
    expect(Course::query()->count())->toBe(0);
});

test('optional transcript is stored on step text', function () {
    LmsServer::tool(CreateVideoCourse::class, videoCoursePayload([
        'lessons' => [
            [
                'name' => 'Lesson one',
                'steps' => [
                    [
                        'name' => 'Talk track',
                        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
                        'text' => 'This is the transcript.',
                        'is_optional' => true,
                    ],
                ],
            ],
        ],
    ]))->assertOk();

    $step = Step::query()->first();
    expect($step->text)->toBe('This is the transcript.')
        ->and($step->is_optional)->toBeTrue();
});

test('list_courses and get_course include lessons and steps', function () {
    LmsServer::tool(CreateVideoCourse::class, videoCoursePayload())->assertOk();
    $course = Course::query()->first();

    $list = LmsServer::tool(ListCourses::class, []);
    $list->assertOk()->assertSee('DNS Cloudflare')->assertSee('Welcome video');

    $get = LmsServer::tool(GetCourse::class, ['id' => $course->id]);
    $get->assertOk()
        ->assertSee('dns-cloudflare')
        ->assertSee('Getting started')
        ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ');
});

test('update_course and delete_course work', function () {
    LmsServer::tool(CreateVideoCourse::class, videoCoursePayload())->assertOk();
    $course = Course::query()->first();

    LmsServer::tool(UpdateCourse::class, [
        'id' => $course->id,
        'name' => 'DNS Updated',
        'is_private' => false,
        'description' => 'Updated description',
    ])->assertOk()->assertSee('DNS Updated');

    $course->refresh();
    expect($course->name)->toBe('DNS Updated')
        ->and($course->is_private)->toBeFalse()
        ->and($course->description)->toBe('Updated description');

    LmsServer::tool(DeleteCourse::class, ['id' => $course->id])->assertOk();

    expect(Course::query()->count())->toBe(0)
        ->and(Lesson::query()->count())->toBe(0)
        ->and(Step::query()->count())->toBe(0)
        ->and(Video::query()->count())->toBe(0);
});

test('granular lesson and step tools create update and delete', function () {
    $course = Course::factory()->create([
        'name' => 'Manual Course',
        'slug' => 'manual-course',
        'external_id' => 'manual_course',
        'is_private' => true,
    ]);

    LmsServer::tool(CreateLesson::class, [
        'course_id' => $course->id,
        'name' => 'Lesson A',
    ])->assertOk();

    $lesson = Lesson::query()->first();
    expect($lesson->slug)->toBe('lesson-a')->and($lesson->order)->toBe(1);

    LmsServer::tool(UpdateLesson::class, [
        'id' => $lesson->id,
        'name' => 'Lesson A updated',
    ])->assertOk()->assertSee('Lesson A updated');

    LmsServer::tool(CreateVideoStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Step one',
        'video_url' => 'https://vimeo.com/226053498',
        'text' => 'Vimeo transcript',
    ])->assertOk();

    $step = Step::query()->first();
    $video = Video::query()->first();
    expect($step->slug)->toBe('lesson-a-step-one')
        ->and($step->text)->toBe('Vimeo transcript')
        ->and($video->url)->toBe('https://player.vimeo.com/video/226053498');

    LmsServer::tool(UpdateStep::class, [
        'id' => $step->id,
        'name' => 'Step one updated',
        'video_name' => 'Renamed video',
    ])->assertOk()->assertSee('Step one updated');

    expect(Video::query()->first()->name)->toBe('Renamed video');

    LmsServer::tool(DeleteStep::class, ['id' => $step->id])->assertOk();
    expect(Step::query()->count())->toBe(0)->and(Video::query()->count())->toBe(0);

    LmsServer::tool(DeleteLesson::class, ['id' => $lesson->id])->assertOk();
    expect(Lesson::query()->count())->toBe(0);
});
