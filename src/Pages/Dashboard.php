<?php

namespace Tapp\FilamentLms\Pages;

use Illuminate\Support\Facades\Auth;
use Tapp\FilamentLms\Models\Course;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament-lms::pages.dashboard';

    protected static string $routePath = '/';

    protected static ?string $title = 'Courses';

    public $courses;

    public function mount()
    {
        $courses = Course::visible()->get();
        if (config('filament-lms.restrict_course_visibility') && Auth::check()) {
            $user = Auth::user();
            if (method_exists($user, 'isCourseVisibleForUser')) {
                $courses = $courses->filter(function ($course) use ($user) {
                    return $user->isCourseVisibleForUser($course);
                })->values();
            }
        }
        $this->courses = $courses;
    }
}
