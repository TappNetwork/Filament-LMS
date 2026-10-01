<?php

declare(strict_types=1);

use Devtical\Sanctum\Pages\Sanctum;
use Devtical\Sanctum\SanctumPlugin;
use Illuminate\Support\Facades\Gate;
use Tapp\FilamentLms\FilamentLmsServiceProvider;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    if (! class_exists(SanctumPlugin::class)) {
        $this->markTestSkipped('devtical/filament-sanctum is required for Sanctum authorization tests.');
    }
});

test('authorize_lms_admin enables the sanctum gate and disables the package user menu', function () {
    config([
        'filament-lms.sanctum_ui.enabled' => true,
        'filament-lms.sanctum_ui.authorize_lms_admin' => true,
        'filament-sanctum.authorization.enabled' => false,
        'filament-sanctum.navigation.user_menu.enabled' => true,
    ]);

    configureSanctumAuthorization();

    expect(config('filament-sanctum.authorization.enabled'))->toBeTrue()
        ->and(config('filament-sanctum.authorization.gate'))->toBe('lms-mcp-token')
        ->and(config('filament-sanctum.navigation.user_menu.enabled'))->toBeFalse()
        ->and(Gate::has('lms-mcp-token'))->toBeTrue();
});

test('authorize_lms_admin false leaves sanctum authorization untouched', function () {
    config([
        'filament-lms.sanctum_ui.enabled' => true,
        'filament-lms.sanctum_ui.authorize_lms_admin' => false,
        'filament-sanctum.authorization.enabled' => false,
        'filament-sanctum.authorization.gate' => null,
        'filament-sanctum.navigation.user_menu.enabled' => true,
    ]);

    configureSanctumAuthorization();

    expect(config('filament-sanctum.authorization.enabled'))->toBeFalse()
        ->and(config('filament-sanctum.navigation.user_menu.enabled'))->toBeTrue();
});

test('sanctum page allows lms admins and denies other users', function () {
    config([
        'filament-lms.sanctum_ui.enabled' => true,
        'filament-lms.sanctum_ui.authorize_lms_admin' => true,
    ]);

    configureSanctumAuthorization();

    $admin = new class extends TestUser
    {
        public function isLmsAdmin(): bool
        {
            return true;
        }
    };
    $admin->forceFill([
        'name' => 'Admin',
        'email' => 'sanctum-admin@example.com',
        'password' => bcrypt('password'),
    ])->save();

    $manager = new class extends TestUser
    {
        public function isLmsAdmin(): bool
        {
            return false;
        }
    };
    $manager->forceFill([
        'name' => 'Manager',
        'email' => 'sanctum-manager@example.com',
        'password' => bcrypt('password'),
    ])->save();

    $this->actingAs($admin);
    expect(Sanctum::canAccess())->toBeTrue();

    $this->actingAs($manager);
    expect(Sanctum::canAccess())->toBeFalse();
});

function configureSanctumAuthorization(): void
{
    $provider = app()->getProvider(FilamentLmsServiceProvider::class);

    expect($provider)->not->toBeNull();

    $method = new ReflectionMethod($provider, 'configureSanctumAuthorization');
    $method->invoke($provider);
}
