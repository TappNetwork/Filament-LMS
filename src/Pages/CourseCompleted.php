<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentLms\Models\Course;

class CourseCompleted extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static string $view = 'filament-lms::pages.course-completed';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = '{courseSlug}/completed';

    protected static ?string $title = 'Course Completed';

    protected array $extraBodyAttributes = ['class' => 'course-layout'];

    public $course;

    public function mount($courseSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();

        if (! $this->course->completed_at) {
            return redirect()->to($this->course->linkToCurrentStep());
        }
    }
}
