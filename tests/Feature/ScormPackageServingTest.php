<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Services\CommonCartridge\CommonCartridgeImportService;
use Tapp\FilamentLms\Services\CommonCartridge\ManifestParser;
use Tapp\FilamentLms\Services\CommonCartridge\ScormPackageStorage;
use Tapp\FilamentLms\Tests\TestUser;

test('authenticated user can load scorm package entry file', function () {
    config([
        'filament-lms.common_cartridge_import.retain_extracted_packages' => true,
        'filament-lms.user_model' => TestUser::class,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'learner@example.com',
        'password' => bcrypt('password'),
    ]);

    $fixturePath = realpath(__DIR__.'/../fixtures/articulate-rise');
    $service = new CommonCartridgeImportService(new ManifestParser, new ScormPackageStorage);
    $result = $service->import($fixturePath);
    $course = $result['course'];

    $this->actingAs($user);

    $document = Document::query()->first();
    expect($document)->not->toBeNull();

    $response = $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'scormcontent/index.html',
    ]));

    $response->assertSuccessful();
});

test('serves scorm package stored under legacy storage path when local disk uses private root', function () {
    config([
        'filament-lms.user_model' => TestUser::class,
        'filesystems.disks.local.root' => storage_path('app/private'),
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'legacy-learner@example.com',
        'password' => bcrypt('password'),
    ]);

    $packageId = (string) Str::uuid();
    $relativePath = 'lms-scorm-packages/'.$packageId;
    $legacyRoot = storage_path('app/'.$relativePath);
    if (! is_dir($legacyRoot)) {
        mkdir($legacyRoot, 0755, true);
    }
    file_put_contents($legacyRoot.'/index.html', '<html><body>Legacy package</body></html>');

    $course = Course::factory()->create([
        'name' => 'Legacy SCORM Course',
        'slug' => 'legacy-scorm-course',
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Home',
        'package_disk' => 'local',
        'package_path' => $relativePath,
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'index.html',
    ]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/html');
});

test('serves nested package assets via path-based urls', function () {
    config([
        'filament-lms.user_model' => TestUser::class,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'nested-assets@example.com',
        'password' => bcrypt('password'),
    ]);

    $packageId = (string) Str::uuid();
    $relativePath = 'lms-scorm-packages/'.$packageId;
    $packageRoot = storage_path('app/'.$relativePath);
    if (! is_dir($packageRoot)) {
        mkdir($packageRoot, 0755, true);
    }
    mkdir($packageRoot.'/story_content', 0755, true);
    file_put_contents($packageRoot.'/index.html', '<html><script src="story_content/triggers.js"></script></html>');
    file_put_contents($packageRoot.'/story_content/triggers.js', 'window.triggersLoaded = true;');

    $course = Course::factory()->create([
        'name' => 'Nested Assets Course',
        'slug' => 'nested-assets-course',
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Home',
        'package_disk' => 'local',
        'package_path' => $relativePath,
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $this->actingAs($user);

    $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'index.html',
    ]))->assertSuccessful();

    $launchUrl = route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'index.html',
    ]);
    expect($launchUrl)->toContain('/lms/scorm-package/'.$document->id.'/index.html');
    expect($launchUrl)->not->toContain('?entry=');

    $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'story_content/triggers.js',
    ]))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/javascript');
});

test('serves svg package assets with script-blocking security headers', function () {
    config([
        'filament-lms.user_model' => TestUser::class,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'svg-assets@example.com',
        'password' => bcrypt('password'),
    ]);

    $packageId = (string) Str::uuid();
    $relativePath = 'lms-scorm-packages/'.$packageId;
    $packageRoot = storage_path('app/'.$relativePath);
    if (! is_dir($packageRoot)) {
        mkdir($packageRoot, 0755, true);
    }
    file_put_contents($packageRoot.'/icon.svg', '<svg><script>alert(1)</script></svg>');

    $course = Course::factory()->create([
        'name' => 'SVG Assets Course',
        'slug' => 'svg-assets-course',
        'is_private' => false,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Home',
        'package_disk' => 'local',
        'package_path' => $relativePath,
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $this->actingAs($user);

    $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'icon.svg',
    ]))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/svg+xml')
        ->assertHeader('content-security-policy', "script-src 'none'; sandbox")
        ->assertHeader('x-content-type-options', 'nosniff');
});

test('serves html5 package index with injected bridge for embedded player courses', function () {
    config([
        'filament-lms.user_model' => TestUser::class,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'html5-bridge@example.com',
        'password' => bcrypt('password'),
    ]);

    $packageId = (string) Str::uuid();
    $relativePath = 'lms-scorm-packages/'.$packageId;
    $packageRoot = storage_path('app/'.$relativePath);
    if (! is_dir($packageRoot)) {
        mkdir($packageRoot, 0755, true);
    }
    file_put_contents($packageRoot.'/index.html', '<html><body>Player</body></html>');

    $course = Course::factory()->create([
        'name' => 'HTML5 Bridge Course',
        'slug' => 'html5-bridge-course',
        'is_private' => false,
        'embedded_player' => true,
        'completion_mode' => CompletionMode::Html5,
    ]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $document = Document::query()->create([
        'name' => 'Home',
        'package_disk' => 'local',
        'package_path' => $relativePath,
        'package_launch_path' => 'index.html',
    ]);
    Step::factory()->create([
        'lesson_id' => $lesson->id,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'index.html',
    ]));

    $response->assertSuccessful();
    expect($response->getContent())->toContain('lms-html5-progress')
        ->and($response->getContent())->toContain('hasSeenSlideChange')
        ->and($response->getContent())->toContain('count === 1 && isBeforeUnload');
});
