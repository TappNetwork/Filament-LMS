<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Concerns\CourseLayout;

class CourseCompleted extends Page
{
    use CourseLayout;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static string $view = 'filament-lms::pages.course-completed';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = '{courseSlug}/completed';

    protected static ?string $title = 'Course Completed';

    public $course;

    public function mount($courseSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();

        if (! $this->course->completed_at) {
            return redirect()->to($this->course->linkToCurrentStep());
        }

        $this->registerCourseLayout();
    }

    public function downloadCertificate()
    {
        return response()->download(route('certificates.download', [auth()->user()->id, $this->course->id]));
    }
}
