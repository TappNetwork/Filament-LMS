<?php

declare(strict_types=1);

use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Services\CommonCartridge\CommonCartridgeImportService;
use Tapp\FilamentLms\Services\CommonCartridge\ManifestParser;
use Tapp\FilamentLms\Services\CommonCartridge\ScormPackageStorage;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);

    $riseIndex = __DIR__.'/../fixtures/articulate-rise/scormcontent/index.html';
    if (! is_file($riseIndex)) {
        if (! is_dir(dirname($riseIndex))) {
            mkdir(dirname($riseIndex), 0755, true);
        }
        file_put_contents($riseIndex, '<html><body>Rise fixture</body></html>');
    }

    $html5Index = __DIR__.'/../fixtures/storyline-html5/index.html';
    if (! is_file($html5Index)) {
        file_put_contents($html5Index, '<html><body>HTML5 fixture</body></html>');
    }
});

test('manifest parser extracts course title and single lesson with one step from fixture', function () {
    $fixturePath = __DIR__.'/../fixtures/common-cartridge';
    $parser = new ManifestParser;
    $manifest = $parser->parse($fixturePath);

    expect($manifest->courseTitle)->toBe('Child Outcomes Summary');
    expect($manifest->lessons)->toHaveCount(1);
    expect($manifest->lessons[0]->title)->toBeIn(['Content', 'Child Outcomes Summary']);
    expect($manifest->lessons[0]->steps[0]->title)->toBe('Child Outcomes Summary');
    expect($manifest->lessons[0]->steps)->toHaveCount(1);
    expect($manifest->lessons[0]->steps[0]->resourceIdentifier)->toBe('__6c3M623PUXY_course_id_RES');
    expect($manifest->resources)->toHaveCount(1);
    $resource = $manifest->resources['__6c3M623PUXY_course_id_RES'];
    expect($resource->type)->toBe('webcontent');
    expect($resource->href)->toBe('index_lms.html');
});

test('import service uniquifies duplicate course names', function () {
    $fixturePath = realpath(__DIR__.'/../fixtures/common-cartridge');
    $service = new CommonCartridgeImportService(new ManifestParser, new ScormPackageStorage);

    $service->import($fixturePath);
    $second = $service->import($fixturePath);

    expect($second['course']->name)->toBe('Child Outcomes Summary-1');
});

test('import service creates course lesson step and document from fixture', function () {
    $fixturePath = realpath(__DIR__.'/../fixtures/common-cartridge');
    expect($fixturePath)->not->toBeFalse();
    if (! is_file($fixturePath.'/index_lms.html')) {
        test()->markTestSkipped('Fixture must contain index_lms.html');
    }
    $parser = new ManifestParser;
    $service = new CommonCartridgeImportService($parser, new ScormPackageStorage);

    $result = $service->import($fixturePath);

    expect($result['course'])->toBeInstanceOf(Course::class);
    expect($result['course']->name)->toBe('Child Outcomes Summary');
    expect($result['lessons_created'])->toBe(1);
    expect($result['steps_created'])->toBe(1);

    $course = $result['course'];
    $course->load('lessons.steps.material');
    expect($course->lessons)->toHaveCount(1);
    $lesson = $course->lessons->first();
    expect($lesson->steps)->toHaveCount(1);
    $step = $lesson->steps->first();
    expect($step->material_type)->toBe('document');
    expect($step->material_id)->not->toBeNull();
    $document = $step->material;
    expect($document)->toBeInstanceOf(Document::class);
    expect($document->getFirstMedia())->not->toBeNull();
});

test('parser throws when imsmanifest.xml is missing', function () {
    $parser = new ManifestParser;
    $parser->parse('/nonexistent/path');
})->throws(InvalidArgumentException::class);

test('manifest parser uses Articulate frame.xml when present for lessons and steps', function () {
    $fixturePath = __DIR__.'/../fixtures/common-cartridge-with-articulate';
    $parser = new ManifestParser;
    $manifest = $parser->parse($fixturePath);

    expect($manifest->courseTitle)->toBe('Child Outcomes Summary');
    expect($manifest->lessons)->not->toBeEmpty();
    expect($manifest->lessons[0]->title)->toBe('Home');
    expect($manifest->lessons[0]->steps)->not->toBeEmpty();
    expect($manifest->lessons[0]->steps[0]->title)->toBe('Home');
});

test('manifest parser prefers Rise scormdriver launch path', function () {
    $fixturePath = __DIR__.'/../fixtures/articulate-rise';
    $parser = new ManifestParser;
    $manifest = $parser->parse($fixturePath);

    expect($manifest->preferredLaunchHref)->toBe('scormdriver/indexAPI.html');
    expect($manifest->courseTitle)->toBe('A Quick Guide to the Completion of the MDA');
    expect($manifest->lessons[0]->steps[0]->resourceIdentifier)->toBe('r1');
    expect($manifest->resources['r1']->type)->toBe('webcontent');
});

test('import service creates Rise course with scorm package on document', function () {
    config([
        'filament-lms.common_cartridge_import.retain_extracted_packages' => true,
    ]);

    $fixturePath = realpath(__DIR__.'/../fixtures/articulate-rise');
    expect($fixturePath)->not->toBeFalse();

    $preManifest = (new ManifestParser)->parse($fixturePath);
    expect($preManifest->lessons[0]->steps[0]->resourceIdentifier)->toBe('r1');
    expect($preManifest->preferredLaunchHref)->toBe('scormdriver/indexAPI.html');
    expect(is_file($fixturePath.'/'.$preManifest->preferredLaunchHref))->toBeTrue();

    $service = new CommonCartridgeImportService(new ManifestParser, new ScormPackageStorage);

    $result = $service->import($fixturePath);
    expect(Document::query()->count())->toBeGreaterThan(0);

    $course = $result['course']->load('lessons.steps.material');
    $step = $course->lessons->first()->steps->first();
    expect($step->material_type)->toBe('document');
    $document = $step->material;

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->package_path)->not->toBeNull();
    expect($document->package_launch_path)->toBe('scormdriver/indexAPI.html');
    expect($course->embedded_player)->toBeTrue();
    expect($course->completion_mode)->toBe(CompletionMode::Scorm12);
});

test('html5 package parser imports Storyline HTML5 without imsmanifest', function () {
    $fixturePath = realpath(__DIR__.'/../fixtures/storyline-html5');
    expect($fixturePath)->not->toBeFalse();
    expect(is_file($fixturePath.'/index.html'))->toBeTrue();

    $parser = new ManifestParser;
    $manifest = $parser->parse($fixturePath);

    expect($manifest->lessons)->not->toBeEmpty();
    expect($manifest->preferredLaunchHref)->toBe('index.html');

    $service = new CommonCartridgeImportService($parser, new ScormPackageStorage);
    $result = $service->import($fixturePath);

    expect($result['lessons_created'])->toBeGreaterThan(0);
    expect($result['steps_created'])->toBeGreaterThan(0);
});
