<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Tapp\FilamentLms\Mcp\LmsServer;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    if (! class_exists(LmsServer::class)) {
        $this->markTestSkipped('laravel/mcp is required to run LMS MCP HTTP tests.');
    }
});

test('http mcp rejects unauthenticated requests', function () {
    $this->postJson('/mcp/lms')->assertUnauthorized();
});

test('http mcp rejects non-admin sanctum users', function () {
    $user = TestUser::query()->create([
        'name' => 'Member',
        'email' => 'member@example.com',
        'password' => bcrypt('password'),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/mcp/lms')->assertForbidden();
});

test('http mcp allows an authenticated lms admin past the gate', function () {
    $admin = new class extends TestUser
    {
        public function isLmsAdmin(): bool
        {
            return true;
        }
    };
    $admin->forceFill([
        'name' => 'Admin',
        'email' => 'http-admin@example.com',
        'password' => bcrypt('password'),
    ])->save();

    Sanctum::actingAs($admin);

    $response = $this->postJson('/mcp/lms');

    expect($response->status())->not->toBe(401)
        ->and($response->status())->not->toBe(403);
});
