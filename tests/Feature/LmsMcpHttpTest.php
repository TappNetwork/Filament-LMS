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

test('http mcp list_courses succeeds with a list_courses token ability', function () {
    $admin = makeLmsAdmin('list-token@example.com');
    $token = $admin->createToken('lms-mcp', ['list_courses'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/mcp/lms', mcpHttpToolCallPayload('list_courses'), [
        'Accept' => 'application/json, text/event-stream',
    ]);

    expect($response->status())->not->toBe(401)
        ->and($response->status())->not->toBe(403)
        ->and($response->getContent())->not->toContain('Token lacks ability');
});

test('http mcp delete_course is denied when the token only has list_courses', function () {
    $admin = makeLmsAdmin('deny-delete@example.com');
    $token = $admin->createToken('lms-mcp', ['list_courses'])->plainTextToken;

    $response = $this->withToken($token)->postJson('/mcp/lms', mcpHttpToolCallPayload('delete_course', [
        'id' => 1,
    ]), [
        'Accept' => 'application/json, text/event-stream',
    ]);

    expect($response->getContent())->toContain('Token lacks ability delete_course');
});

test('http mcp delete_course succeeds with a wildcard token', function () {
    $admin = makeLmsAdmin('wildcard@example.com');
    $token = $admin->createToken('lms-mcp')->plainTextToken;

    $course = \Tapp\FilamentLms\Models\Course::query()->create([
        'name' => 'Wildcard Course',
        'slug' => 'wildcard-course',
        'external_id' => 'wildcard_course',
        'is_private' => true,
    ]);

    $response = $this->withToken($token)->postJson('/mcp/lms', mcpHttpToolCallPayload('delete_course', [
        'id' => $course->id,
    ]), [
        'Accept' => 'application/json, text/event-stream',
    ]);

    expect($response->getContent())->not->toContain('Token lacks ability')
        ->and(\Tapp\FilamentLms\Models\Course::query()->whereKey($course->id)->exists())->toBeFalse();
});

function makeLmsAdmin(string $email): TestUser
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
        'email' => $email,
        'password' => bcrypt('password'),
    ])->save();

    return $admin;
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array{jsonrpc: string, id: int, method: string, params: array{name: string, arguments: array<string, mixed>}}
 */
function mcpHttpToolCallPayload(string $tool, array $arguments = []): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool,
            'arguments' => $arguments,
        ],
    ];
}
