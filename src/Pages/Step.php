<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step as StepModel;
use Filament\Support\Enums\MaxWidth;
use Livewire\Attributes\On;

class Step extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament-lms::pages.step';

    protected static ?string $slug = '{courseSlug}/{lessonSlug}/{stepSlug}';

    // protected array $extraBodyAttributes = ['class' => 'course-layout'];

    public $course;
    public $lesson;
    public $step;

    public function mount($courseSlug, $lessonSlug, $stepSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();
        $this->course->loadProgress();
        $this->lesson = $this->course->lessons->where('slug', $lessonSlug)->firstOrFail();
        $this->step = $this->lesson->steps->where('slug', $stepSlug)->firstOrFail();
        $this->heading = $this->step->name;

        if (! $this->step->available) {
            return redirect()->to($this->course->linkToCurrentStep());
        }
    }

    #[On('complete-step')]
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

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }
}
