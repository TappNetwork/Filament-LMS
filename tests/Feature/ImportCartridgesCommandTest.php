<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);
    TestUser::query()->create([
        'name' => 'Import User',
        'first_name' => 'Import',
        'last_name' => 'User',
        'email' => 'import@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('import cartridges command queues job for zip in directory', function () {
    Queue::fake();

    $directory = storage_path('app/test-cartridge-imports');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $zipPath = $directory.'/sample.zip';
    copy(__DIR__.'/../fixtures/common-cartridge.zip', $zipPath);

    $this->artisan('filament-lms:import-cartridges', [
        '--path' => $directory,
        '--user-id' => 1,
    ])->assertSuccessful();

    Queue::assertPushed(ImportCommonCartridgeJob::class);
});
