<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Tapp\FilamentLms\Concerns\CourseLayout;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step as StepModel;

class Step extends Page
{
    use CourseLayout;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament-lms::pages.step';

    protected static ?string $slug = 'courses/{courseSlug}/{lessonSlug}/{stepSlug}';

    public Course $course;

    public Lesson $lesson;

    public StepModel $step;

    public function mount($courseSlug, $lessonSlug, $stepSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();
        $this->course->loadProgress();
        $this->lesson = $this->course->lessons->where('slug', $lessonSlug)->firstOrFail();
        $this->step = $this->lesson->steps->where('slug', $stepSlug)->firstOrFail();
        // @phpstan-ignore-next-line
        $this->heading = $this->step->name;

        $user = Auth::user();
        // @phpstan-ignore-next-line
        if (! $user || ! $user->canAccessStep($this->step)) {
            return redirect()->to($this->course->linkToCurrentStep());
        }

        $this->registerCourseLayout();
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('viewAllCourses')
                ->label('View All Courses')
                ->color('gray')
                ->url(Dashboard::getUrl()),
        ];

        // Add Edit button for users who can edit the step
        if (Auth::check()) {
            $user = Auth::user();
            // @phpstan-ignore-next-line
            if ($user && $user->canEditStep($this->step)) {
                $actions[] = Action::make('edit')
                    ->label('Edit')
                    ->color('primary')
                    ->url(route('filament.admin.resources.lms.steps.edit', $this->step))
                    ->icon('heroicon-o-pencil');
            }
        }

        return $actions;
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

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function viewAllCourses()
    {
        return redirect()->to(Dashboard::getUrl());
    }
}
