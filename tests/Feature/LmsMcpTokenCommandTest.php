<?php

declare(strict_types=1);

use Laravel\Sanctum\PersonalAccessToken;
use Tapp\FilamentLms\Tests\TestUser;

test('lms mcp token command fails when the user does not exist', function () {
    $this->artisan('lms:mcp-token', ['email' => 'missing@example.com'])
        ->expectsOutputToContain('No user found')
        ->assertFailed();
});

test('lms mcp token command fails when the user is not an lms admin', function () {
    TestUser::query()->create([
        'name' => 'Member',
        'email' => 'member@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->artisan('lms:mcp-token', ['email' => 'member@example.com'])
        ->expectsOutputToContain('is not an LMS admin')
        ->assertFailed();
});

test('lms mcp token command prints claude json for an lms admin', function () {
    $admin = new class extends TestUser
    {
        public function isLmsAdmin(): bool
        {
            return true;
        }
    };
    $admin->setTable('users');
    $admin->forceFill([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ])->save();

    config(['filament-lms.user_model' => $admin::class]);

    $this->artisan('lms:mcp-token', [
        'email' => 'admin@example.com',
        '--server-key' => 'check-lms-staging',
    ])
        ->expectsOutputToContain('Token minted')
        ->expectsOutputToContain('check-lms-staging')
        ->assertSuccessful();

    expect(PersonalAccessToken::query()->where('name', 'lms-mcp')->exists())->toBeTrue();
});
