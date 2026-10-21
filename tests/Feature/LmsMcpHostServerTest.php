<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Laravel\Sanctum\Sanctum;
use Tapp\FilamentLms\FilamentLmsServiceProvider;
use Tapp\FilamentLms\Http\Middleware\EnsureLmsMcpAdmin;
use Tapp\FilamentLms\Mcp\LmsServer;
use Tapp\FilamentLms\Tests\Support\HostMcpServer;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    if (! class_exists(LmsServer::class)) {
        $this->markTestSkipped('laravel/mcp is required for host MCP server tests.');
    }
});

function resetMcpRegistrarAndRoutes(): void
{
    app()->instance(Registrar::class, new Registrar);
    Mcp::clearResolvedInstances();
    Route::setRoutes(new RouteCollection);
}

function runPackageMcpRegistration(): void
{
    $provider = app()->getProvider(FilamentLmsServiceProvider::class);

    (new ReflectionMethod($provider, 'registerMcpServer'))->invoke($provider);
}

/**
 * @return list<string>
 */
function listMcpToolNamesOverHttp(): array
{
    $admin = new class extends TestUser
    {
        public function isLmsAdmin(): bool
        {
            return true;
        }
    };
    $admin->forceFill([
        'name' => 'Admin',
        'email' => 'host-admin@example.com',
        'password' => bcrypt('password'),
    ])->save();

    Sanctum::actingAs($admin);

    $response = test()->postJson('/mcp/lms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream']);

    $response->assertOk();

    return array_column($response->json('result.tools'), 'name');
}

test('LmsServer::TOOLS is the tool list the package server serves', function () {
    $names = listMcpToolNamesOverHttp();

    expect($names)->toHaveCount(count(LmsServer::TOOLS))
        ->toContain('list_courses', 'create_video_course', 'delete_step')
        ->not->toContain('host_ping');
});

test('the package registers its own servers when the host has none', function () {
    resetMcpRegistrarAndRoutes();

    runPackageMcpRegistration();

    expect(Mcp::getLocalServer('filament-lms'))->not->toBeNull()
        ->and(Mcp::getWebServer('mcp/lms'))->not->toBeNull()
        ->and(listMcpToolNamesOverHttp())->not->toContain('host_ping');
});

test('the package steps aside for a host server on the same handle and path', function () {
    resetMcpRegistrarAndRoutes();

    Mcp::local('filament-lms', HostMcpServer::class);
    $hostLocal = Mcp::getLocalServer('filament-lms');
    $hostRoute = Mcp::web('/mcp/lms', HostMcpServer::class)
        ->middleware(['auth:sanctum', 'throttle:mcp', EnsureLmsMcpAdmin::class]);

    runPackageMcpRegistration();

    expect(Mcp::getLocalServer('filament-lms'))->toBe($hostLocal)
        ->and(Mcp::getWebServer('mcp/lms'))->toBe($hostRoute)
        ->and(listMcpToolNamesOverHttp())->toContain('host_ping', 'list_courses', 'delete_step');
});

test('mcp.enabled and mcp.web set to false skip package registration', function () {
    resetMcpRegistrarAndRoutes();
    config(['filament-lms.mcp.enabled' => false, 'filament-lms.mcp.web' => false]);

    runPackageMcpRegistration();

    expect(Mcp::getLocalServer('filament-lms'))->toBeNull()
        ->and(Mcp::getWebServer('mcp/lms'))->toBeNull();
});

test('a host server that uses HasSanctumAbilities lists package and host abilities', function () {
    $abilities = HostMcpServer::sanctumAbilities();

    expect($abilities)->toHaveKey('host_ping')
        ->and(array_diff_key(LmsServer::sanctumAbilities(), $abilities))->toBe([]);
});
