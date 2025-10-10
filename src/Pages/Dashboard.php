<?php

namespace Tapp\FilamentLms\Pages;

use Illuminate\Support\Facades\Auth;
use Tapp\FilamentLms\Models\Course;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament-lms::pages.dashboard';

    protected static string $routePath = '/';

    protected static ?string $title = 'Courses';

    public $courses;

    public function mount()
    {
        $user = Auth::user();
        
        if ($user) {
            // Use the new accessibleTo scope for better performance
            $courses = Course::accessibleTo($user)->get();
        } else {
            // For non-authenticated users, only show public courses (not private)
            $courses = Course::where('is_private', false)->get();
        }
        
        $this->courses = $courses;
    }
}
