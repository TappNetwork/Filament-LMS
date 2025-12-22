<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentLms\Concerns\CourseLayout;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Pages\Dashboard;

class CourseCompleted extends Page
{
    use CourseLayout;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected string $view = 'filament-lms::pages.course-completed';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = '{courseSlug}/completed';

    protected static ?string $title = 'Course Completed';

    public $course;

    public function mount($courseSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();

        // If course has no steps, redirect to dashboard
        if (! $this->course->steps()->exists()) {
            return redirect()->to(Dashboard::getUrl());
        }

        if (! $this->course->completed_at) {
            $currentStepUrl = $this->course->linkToCurrentStep();
            // If linkToCurrentStep returns empty (no steps), redirect to dashboard
            if (empty($currentStepUrl)) {
                return redirect()->to(Dashboard::getUrl());
            }

            return redirect()->to($currentStepUrl);
        }

        $this->registerCourseLayout();
    }

    public function downloadCertificate()
    {
        return response()->download(route('certificates.download', [auth()->user()->id, $this->course->id]));
    }
}
