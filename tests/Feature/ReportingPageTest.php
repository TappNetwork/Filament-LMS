<?php

use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Tapp\FilamentLms\Pages\Reporting;

test('users without permission cannot access reporting page', function () {
    // Create a mock user
    $user = new class extends User {
        public function hasRole($role) {
            return false;
        }
        public function hasPermission($permission) {
            return false;
        }
    };

    // Mock the Gate to deny access
    Gate::define('viewLmsReporting', fn ($user) => false);

    // Login the user
    actingAs($user);

    // Assert the page cannot be accessed
    expect(Reporting::canAccess())->toBeFalse();
});

test('users with permission can access reporting page', function () {
    // Create a mock user with permissions
    $user = new class extends User {
        public function hasRole($role) {
            return $role === 'admin';
        }
        public function hasPermission($permission) {
            return $permission === 'view-lms-reporting';
        }
    };

    // Mock the Gate to allow access
    Gate::define('viewLmsReporting', fn ($user) =>
        $user->hasRole('admin') || $user->hasPermission('view-lms-reporting')
    );

    // Login the user
    actingAs($user);

    // Assert the page can be accessed
    expect(Reporting::canAccess())->toBeTrue();
});

test('unauthenticated users cannot access reporting page', function () {
    // Assert the page cannot be accessed without authentication
    expect(Reporting::canAccess())->toBeFalse();
});
