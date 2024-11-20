<?php

namespace Tapp\FilamentLms\Pages;

use Tapp\FilamentLms\Models\Course;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament-lms::pages.dashboard';

    protected static string $routePath = '/';

    protected static ?string $title = 'Courses';

    // protected static string $layout = 'filament-lms::components.layout.lms';

    public $courses;

    public function mount()
    {
        $this->courses = Course::all();
    }
}
