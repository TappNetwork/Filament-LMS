<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tapp\FilamentLms\FilamentLmsServiceProvider;
use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Services\CommonCartridge\CartridgeImportStarter;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);
    TestUser::query()->create([
        'name' => 'Import User',
        'first_name' => 'Import',
        'last_name' => 'User',
        'email' => 'import-ui@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('service provider raises livewire temporary upload max size from config', function () {
    config([
        'filament-lms.common_cartridge_import.max_upload_size_kb' => 256000,
        'filament-lms.common_cartridge_import.max_upload_time_minutes' => 15,
        'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:12288'],
    ]);

    $provider = app()->getProvider(FilamentLmsServiceProvider::class);
    expect($provider)->not->toBeNull();

    $method = new ReflectionMethod($provider, 'configureLivewireTemporaryUploadLimits');
    $method->invoke($provider);

    expect(config('livewire.temporary_file_upload.rules'))
        ->toContain('max:256000')
        ->not->toContain('max:12288')
        ->and(config('livewire.temporary_file_upload.max_upload_time'))->toBe(15);
});

test('cartridge import starter queues job with path user and tenant', function () {
    Queue::fake();

    $zipPath = storage_path('app/test-cartridge-ui/sample.zip');
    if (! is_dir(dirname($zipPath))) {
        mkdir(dirname($zipPath), 0755, true);
    }
    copy(__DIR__.'/../fixtures/common-cartridge.zip', $zipPath);

    app(CartridgeImportStarter::class)->dispatch($zipPath, 1, 99);

    Queue::assertPushed(ImportCommonCartridgeJob::class, function (ImportCommonCartridgeJob $job) use ($zipPath): bool {
        return $job->storedPath === $zipPath
            && $job->userId === 1
            && $job->tenantId === 99;
    });
});

test('cartridge import starter runs synchronously and creates course', function () {
    Notification::fake();

    $zipPath = storage_path('app/test-cartridge-ui-sync/sample.zip');
    if (! is_dir(dirname($zipPath))) {
        mkdir(dirname($zipPath), 0755, true);
    }
    copy(__DIR__.'/../fixtures/common-cartridge.zip', $zipPath);

    app(CartridgeImportStarter::class)->dispatch($zipPath, 1, null, sync: true);

    expect(Course::query()->where('name', 'Child Outcomes Summary')->exists())->toBeTrue();
});

test('cartridge import starter resolves uploaded file instances', function () {
    $starter = app(CartridgeImportStarter::class);

    $uploadedFile = UploadedFile::fake()->create('package.zip', 100, 'application/zip');

    expect($starter->resolveUploadedFile($uploadedFile))->toBe($uploadedFile)
        ->and($starter->resolveUploadedFile([$uploadedFile]))->toBe($uploadedFile)
        ->and($starter->resolveUploadedFile(null))->toBeNull()
        ->and($starter->resolveUploadedFile('not-a-file'))->toBeNull();
});

test('cartridge import starter throws when stored file is missing', function () {
    app(CartridgeImportStarter::class)->dispatch('/tmp/missing-scorm-package.zip', 1);
})->throws(RuntimeException::class, 'Stored import file not found');

test('multipart upload is disabled by default even when uppy class exists', function () {
    config(['filament-lms.common_cartridge_import.multipart_upload.enabled' => false]);

    expect(CartridgeImportStarter::usesMultipartUpload())->toBeFalse();
});

test('multipart upload requires both config and uppy package', function () {
    config(['filament-lms.common_cartridge_import.multipart_upload.enabled' => true]);

    expect(CartridgeImportStarter::usesMultipartUpload())
        ->toBe(class_exists('SpykApp\\UppyUpload\\Forms\\Components\\UppyUpload'));
});

test('cartridge import starter resolves absolute path from storage relative path', function () {
    $starter = app(CartridgeImportStarter::class);
    $disk = $starter->storageDisk();
    $relativePath = $starter->storageDirectory().'/ui-import-test.zip';

    Storage::disk($disk)->put($relativePath, file_get_contents(__DIR__.'/../fixtures/common-cartridge.zip'));

    $absolutePath = $starter->absolutePathFromStoredRelativePath($relativePath);

    expect($absolutePath)->not->toBeNull()
        ->and(is_file($absolutePath))->toBeTrue()
        ->and($starter->absolutePathFromStoredRelativePath([$relativePath]))->toBe($absolutePath)
        ->and($starter->absolutePathFromStoredRelativePath('missing-relative.zip'))->toBeNull()
        ->and($starter->absolutePathFromStoredRelativePath(null))->toBeNull();

    Storage::disk($disk)->delete($relativePath);
});

test('stage uploaded cartridge stores livewire upload when multipart is disabled', function () {
    config(['filament-lms.common_cartridge_import.multipart_upload.enabled' => false]);

    $starter = app(CartridgeImportStarter::class);
    $uploadedFile = UploadedFile::fake()->create('package.zip', 100, 'application/zip');

    $absolutePath = $starter->stageUploadedCartridge($uploadedFile);

    expect($absolutePath)->not->toBeNull()
        ->and(is_file($absolutePath))->toBeTrue()
        ->and(str_ends_with($absolutePath, '.zip'))->toBeTrue()
        ->and(basename($absolutePath))->toMatch('/^[0-9a-f-]{36}\.zip$/i');

    @unlink($absolutePath);
});

test('stage multipart cartridge renames uppy upload to a unique uuid zip', function () {
    $starter = app(CartridgeImportStarter::class);
    $disk = $starter->storageDisk();
    $sourceRelativePath = $starter->storageDirectory().'/same-package-name.zip';

    Storage::disk($disk)->put($sourceRelativePath, file_get_contents(__DIR__.'/../fixtures/common-cartridge.zip'));

    $firstPath = $starter->stageMultipartCartridge($sourceRelativePath);

    expect($firstPath)->not->toBeNull()
        ->and(is_file($firstPath))->toBeTrue()
        ->and(basename($firstPath))->toMatch('/^[0-9a-f-]{36}\.zip$/i')
        ->and(Storage::disk($disk)->exists($sourceRelativePath))->toBeFalse();

    $secondSource = $starter->storageDirectory().'/same-package-name.zip';
    Storage::disk($disk)->put($secondSource, file_get_contents(__DIR__.'/../fixtures/common-cartridge.zip'));

    $secondPath = $starter->stageMultipartCartridge([$secondSource]);

    expect($secondPath)->not->toBeNull()
        ->and($secondPath)->not->toBe($firstPath)
        ->and(is_file($firstPath))->toBeTrue()
        ->and(is_file($secondPath))->toBeTrue()
        ->and(basename($secondPath))->toMatch('/^[0-9a-f-]{36}\.zip$/i');

    @unlink($firstPath);
    @unlink($secondPath);
});

test('stage multipart cartridge returns null for missing uppy path', function () {
    $starter = app(CartridgeImportStarter::class);

    expect($starter->stageMultipartCartridge('missing-uppy-upload.zip'))->toBeNull()
        ->and($starter->stageMultipartCartridge($starter->storageDirectory().'/missing-uppy-upload.zip'))->toBeNull();
});

test('staging path resolution rejects absolute paths and path traversal', function () {
    $starter = app(CartridgeImportStarter::class);
    $disk = $starter->storageDisk();
    $outsidePath = storage_path('app/outside-scorm-staging.zip');

    file_put_contents($outsidePath, file_get_contents(__DIR__.'/../fixtures/common-cartridge.zip'));

    $traversalRelative = $starter->storageDirectory().'/../outside-scorm-staging.zip';

    expect($starter->absolutePathFromStoredRelativePath($outsidePath))->toBeNull()
        ->and($starter->absolutePathFromStoredRelativePath('/etc/passwd'))->toBeNull()
        ->and($starter->absolutePathFromStoredRelativePath($traversalRelative))->toBeNull()
        ->and($starter->stageMultipartCartridge($outsidePath))->toBeNull()
        ->and($starter->stageMultipartCartridge('/etc/passwd'))->toBeNull()
        ->and($starter->stageMultipartCartridge($traversalRelative))->toBeNull()
        ->and($starter->validatedStagingRelativePath('other-directory/package.zip'))->toBeNull();

    @unlink($outsidePath);
    Storage::disk($disk)->delete($traversalRelative);
});
