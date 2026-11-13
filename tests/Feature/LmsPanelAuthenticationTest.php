<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

// 5.x keeps the LMS panel's own login page. Removing it is a breaking change and ships in 6.0.
it('registers a login page on the lms panel', function (): void {
    expect(Filament::getPanel('lms')->hasLogin())->toBeTrue()
        ->and(Route::has('filament.lms.auth.login'))->toBeTrue();
});

it('sends guests on the lms dashboard to the lms login', function (): void {
    $this->get('/lms')->assertRedirect(route('filament.lms.auth.login'));
});
