<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Pages;

use BackedEnum;
use Exception;
use Filament\Pages\Page;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Concerns\CourseLayout;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Services\CourseEvaluationService;

final class CourseCompleted extends Page
{
    use CourseLayout;

    public $course;

    public bool $qualifiedForCertificate = true;

    public bool $pendingEvaluation = false;

    public ?Course $evaluationCourse = null;

    public ?string $evaluationUrl = null;

    public ?float $overallPercent = null;

    public ?int $requiredPercent = null;

    public array $testStepDetails = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected string $view = 'filament-lms::pages.course-completed';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = '{courseSlug}/completed';

    protected static ?string $title = 'Course Completed';

    public function mount($courseSlug)
    {
        $this->course = Course::where('slug', $courseSlug)->firstOrFail();
        $evaluationService = app(CourseEvaluationService::class);
        $user = Auth::user();

        if (! $user instanceof Authenticatable) {
            return redirect()->to(Dashboard::getUrl());
        }

        $userId = $user->id;

        if ($evaluationService->isEvaluationCourse($this->course)) {
            $activePrimaryCourse = $evaluationService->activePrimaryCourseForEvaluation($this->course, $user);

            if (
                $activePrimaryCourse !== null
                && ! $evaluationService->evaluationCompletedByUser($activePrimaryCourse, $userId)
            ) {
                return redirect()->to(
                    $evaluationService->evaluationUrlForPrimaryCourse($activePrimaryCourse) ?? Dashboard::getUrl(),
                );
            }

            $primaryCourse = $evaluationService->completedPrimaryCourseForEvaluation($this->course, $userId);

            if ($primaryCourse !== null) {
                return redirect()->to(self::getUrl([$primaryCourse->slug]));
            }
        }

        // If course has no steps, redirect to dashboard
        if (! $this->course->steps()->exists()) {
            return redirect()->to(Dashboard::getUrl());
        }

        // Only redirect when the user has not completed all steps. Allow viewing this page when all steps
        // are done even if required_test_percentage is not met (we show retake/certificate state below).
        if (! $this->course->allStepsCompletedByUser($userId)) {
            $currentStepUrl = $this->course->linkToCurrentStep();
            if (empty($currentStepUrl)) {
                return redirect()->to(Dashboard::getUrl());
            }

            return redirect()->to($currentStepUrl);
        }

        if ($evaluationService->isEvaluationCourse($this->course)) {
            return redirect()->to(Dashboard::getUrl());
        }

        if ($evaluationService->hasPendingEvaluationForUser($this->course, $userId)) {
            /** @var Course|null $evaluationCourse */
            $evaluationCourse = $this->course->evaluationCourse;

            $this->pendingEvaluation = true;
            $this->evaluationCourse = $evaluationCourse;
            $this->evaluationUrl = $this->course->evaluationSubmissionUrl();
            $this->course->ensureEvaluationAssigned($userId);
            $this->qualifiedForCertificate = false;

            $this->registerCourseLayout();

            return;
        }

        if ($this->course->required_test_percentage !== null) {
            $testSteps = $this->course->getOrderedTestSteps();
            // Only check test percentage if there are test steps
            if ($testSteps->isNotEmpty()) {
                $overall = $this->course->getOverallTestPercentageForUser($userId);
                if ($overall < (float) $this->course->required_test_percentage) {
                    $this->qualifiedForCertificate = false;
                    $this->overallPercent = $overall;
                    $this->requiredPercent = (int) $this->course->required_test_percentage;
                }
            }
        }

        // Collect test step details for the table
        $this->testStepDetails = $this->getTestStepDetails();

        // Backfill completed_at if user qualifies but it was never set (e.g. course had required_test_percentage but no test steps).
        $this->course->maybeSetCompletedAtForUser($userId);

        // Only show certificate UI when the user has actually completed the course (completed_at set).
        // Prevents 403 on download when e.g. required_test_percentage is set but course has no test steps.
        if ($this->course->completedByUserAt($userId) === null) {
            $this->qualifiedForCertificate = false;
        }

        $this->registerCourseLayout();
    }

    public function downloadCertificate()
    {
        return response()->download(route('certificates.download', [auth()->user()->id, $this->course->id]));
    }

    /**
     * Get test step details for display in the table.
     *
     * @return array<int, array{name: string, total_questions: int, correct_questions: int, percentage: float, url: string}>
     */
    protected function getTestStepDetails(): array
    {
        $testSteps = $this->course->getOrderedTestSteps();
        $userId = auth()->id();
        $details = [];

        foreach ($testSteps as $step) {
            $step->load('material');
            $test = $step->material;

            if (! $test instanceof Test) {
                continue;
            }

            $test->load('rubric');
            $rubric = $test->rubric;

            if (! $rubric instanceof FilamentFormUser) {
                continue;
            }

            $totalQuestions = count($rubric->entry);

            $entry = FilamentFormUser::where('filament_form_id', $test->filament_form_id)
                ->where('user_id', $userId)
                ->when($test->filament_form_user_id, fn ($q) => $q->where('id', '!=', $test->filament_form_user_id))
                ->first();

            if (! $entry) {
                $details[] = [
                    'name' => $step->name,
                    'total_questions' => $totalQuestions,
                    'correct_questions' => 0,
                    'percentage' => 0.0,
                    'url' => Step::getUrlForStep($step),
                ];

                continue;
            }

            $grade = $test->gradeEntry($entry);

            if ($grade instanceof Exception) {
                $details[] = [
                    'name' => $step->name,
                    'total_questions' => $totalQuestions,
                    'correct_questions' => 0,
                    'percentage' => 0.0,
                    'url' => Step::getUrlForStep($step),
                ];

                continue;
            }

            $percentage = (float) $grade;
            $correctQuestions = (int) round(($percentage / 100) * $totalQuestions);

            $details[] = [
                'name' => $step->name,
                'total_questions' => $totalQuestions,
                'correct_questions' => $correctQuestions,
                'percentage' => $percentage,
                'url' => Step::getUrlForStep($step),
            ];
        }

        return $details;
    }
}
