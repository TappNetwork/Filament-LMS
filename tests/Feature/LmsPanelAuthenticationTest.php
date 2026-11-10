<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Tests\TestUser;

it('does not register a login page on the lms panel', function (): void {
    expect(Filament::getPanel('lms')->hasLogin())->toBeFalse()
        ->and(Route::has('filament.lms.auth.login'))->toBeFalse();

    $this->get('/lms/login')->assertNotFound();
});

it('logs out from the lms panel back to the lms home', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Learner',
        'email' => 'learner-logout@example.com',
        'password' => bcrypt('password'),
    ]);

    // TestUser is not a FilamentUser, so Filament only lets it into a panel locally.
    config(['app.env' => 'local']);

    $this->actingAs($user)
        ->withoutMiddleware([PreventRequestForgery::class, VerifyCsrfToken::class])
        ->post(route('filament.lms.auth.logout'))
        ->assertRedirect(url('/lms'));

    $this->assertGuest();
});

it('sends guests on an lms step to the host app login and remembers the step', function (): void {
    Route::get('/login', fn () => 'host login')->name('login');

    $course = Course::factory()->create(['slug' => 'guest-redirect-course']);
    $lesson = Lesson::factory()->create(['course_id' => $course->id, 'slug' => 'lesson-one']);
    Step::factory()->create(['lesson_id' => $lesson->id, 'slug' => 'knowledge-check', 'order' => 1]);

    $stepUrl = url('/lms/courses/guest-redirect-course/lesson-one/knowledge-check');

    $this->get($stepUrl)
        ->assertRedirect(route('login'))
        ->assertSessionHas('url.intended', $stepUrl);
});

it('sends guests on the lms dashboard to the host app login', function (): void {
    Route::get('/login', fn () => 'host login')->name('login');

    $this->get('/lms')
        ->assertRedirect(route('login'))
        ->assertSessionHas('url.intended', url('/lms'));
});
