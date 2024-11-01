<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step as StepModel;

class Step extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament-lms::pages.step';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = '{courseSlug}/{lessonSlug}/{stepSlug}';

    public $course;
    public $lesson;
    public $step;

    public function mount($courseSlug, $lessonSlug, $stepSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();
        $this->lesson = $this->course->lessons()->where('slug', $lessonSlug)->firstOrFail();
        $this->step = $this->lesson->steps()->where('slug', $stepSlug)->firstOrFail();
        $this->heading = $this->step->name;
    }

    public function complete()
    {
        $nextStep = $this->step->complete();

        if (! $this->step->last_step) {
            return redirect()->to(Step::getUrlForStep($nextStep));
        }

        return redirect()->to(CourseCompleted::getUrl([$this->course->slug]));
    }

    public static function getUrlForStep(StepModel $step)
    {
        return static::getUrl([$step->lesson->course->slug, $step->lesson->slug, $step->slug]);
    }
}
