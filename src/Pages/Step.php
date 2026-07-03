<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Tapp\FilamentLms\Concerns\CourseLayout;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step as StepModel;
use Tapp\FilamentLms\Services\CourseEvaluationService;
use Tapp\FilamentLms\Services\ScormProgressService;

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

        if ($this->course->isEmbeddedPlayer()) {
            $launchStep = $this->course->launchStep();
            if ($launchStep !== null && ! $launchStep->is($this->step)) {
                return redirect()->to(static::getUrlForStep($launchStep));
            }
        }
        // @phpstan-ignore-next-line
        $this->heading = $this->step->name;

        // @phpstan-ignore-next-line
        $user = Auth::user();
        if (! $user instanceof FilamentLmsUserInterface) {
            Log::warning('Step page: User does not implement FilamentLmsUserInterface', [
                'user_id' => $user?->id,
                'step_id' => $this->step->id,
                'step_slug' => $stepSlug,
            ]);

            return redirect()->to($this->course->linkToCurrentStep());
        }

        $canAccess = $user->canAccessStep($this->step);
        $currentStepUrl = $this->course->linkToCurrentStep();
        $requestedStepUrl = static::getUrlForStep($this->step);

        Log::info('Step page access check', [
            'user_id' => $user->id,
            'step_id' => $this->step->id,
            'step_slug' => $stepSlug,
            'can_access' => $canAccess,
            'requested_step_url' => $requestedStepUrl,
            'current_step_url' => $currentStepUrl,
            'step_available' => $this->step->available,
        ]);

        if (! $canAccess) {
            // Prevent redirect loop: if we're already being redirected to the same URL, break the loop
            if ($currentStepUrl === $requestedStepUrl) {
                Log::error('Redirect loop detected in Step page', [
                    'user_id' => $user->id,
                    'step_id' => $this->step->id,
                    'url' => $currentStepUrl,
                ]);

                // Redirect to dashboard instead to break the loop
                return redirect()->to(Dashboard::getUrl());
            }

            return redirect()->to($currentStepUrl);
        }

        $this->registerCourseLayout();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($this->course->isEmbeddedPlayer()) {
            $exitCourse = Action::make('exitCourse')
                ->label('Exit Course')
                ->color('gray')
                ->action(fn () => $this->exitCourse());

            if ($this->shouldRegisterHtml5Bridge()) {
                $user = Auth::user();
                $progressService = app(ScormProgressService::class);
                $needsCompletionConfirm = $user !== null
                    && ! $progressService->courseCompletedByUser($this->course, $user);

                if ($needsCompletionConfirm) {
                    $exitCourse
                        ->requiresConfirmation()
                        ->modalHeading('Exit course')
                        ->modalDescription('Mark this course as complete before returning to your courses?');
                }
            }

            $actions[] = $exitCourse;
        } else {
            $actions[] = Action::make('viewAllCourses')
                ->label('View All Courses')
                ->color('gray')
                ->url(Dashboard::getUrl());
        }

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
        // Use the Model's complete() method which handles all events and progress tracking
        $nextStep = $this->step->complete();

        $user = Auth::user();
        if (
            $this->step->last_step
            && $user instanceof FilamentLmsUserInterface
            && app(CourseEvaluationService::class)->hasPendingEvaluationForUser($this->course, $user->id)
        ) {
            $this->course->ensureEvaluationAssigned($user->id);

            return redirect()->to($this->course->evaluationCourse->linkToCurrentStep());
        }

        if (! $this->step->last_step && $nextStep) {
            return redirect()->to(Step::getUrlForStep($nextStep));
        }

        if (
            $this->step->last_step
            && $this->course->isEvaluationCourse()
            && $user instanceof FilamentLmsUserInterface
        ) {
            $primaryCourse = app(CourseEvaluationService::class)
                ->completedPrimaryCourseForEvaluation($this->course, $user->id);

            if ($primaryCourse !== null) {
                return redirect()->to(CourseCompleted::getUrl([$primaryCourse->slug]));
            }

            return redirect()->to(Dashboard::getUrl());
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

    public function exitCourse(): void
    {
        $user = Auth::user();
        if (! $user instanceof FilamentLmsUserInterface) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        $progressService = app(ScormProgressService::class);

        if (
            $this->course->isEmbeddedPlayer()
            && $this->course->completionMode() === CompletionMode::Html5
            && ! $progressService->courseCompletedByUser($this->course, $user)
        ) {
            $result = $progressService->attemptManualCourseCompletion($this->course, $user);

            if (! $result['ok']) {
                Notification::make()
                    ->title('Cannot mark course complete yet')
                    ->body($result['message'])
                    ->danger()
                    ->send();

                return;
            }
        }

        $this->redirect(Dashboard::getUrl());
    }

    #[On('scorm-course-complete')]
    public function scormCourseComplete(): void
    {
        $user = Auth::user();
        if (! $user instanceof FilamentLmsUserInterface) {
            return;
        }

        app(ScormProgressService::class)->completeAllEligibleSteps($this->course, $user);
    }

    public function shouldRegisterScormBridge(): bool
    {
        return $this->course->isEmbeddedPlayer()
            && $this->course->completionMode() === CompletionMode::Scorm12;
    }

    public function shouldRegisterHtml5Bridge(): bool
    {
        return $this->course->isEmbeddedPlayer()
            && $this->course->completionMode() === CompletionMode::Html5;
    }
}
