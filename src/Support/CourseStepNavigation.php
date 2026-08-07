<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Route;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step as StepModel;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Step;
use Tapp\FilamentLms\Services\CourseEvaluationService;

final class CourseStepNavigation
{
    /**
     * @return array<NavigationGroup>
     */
    public static function groupsForCourse(Course $course): array
    {
        if ($course->isEmbeddedPlayer()) {
            return [];
        }

        $course->loadMissing(['lessons.steps']);

        $evaluationService = app(CourseEvaluationService::class);
        $evaluationPrimaryCourseId = $evaluationService->evaluationPrimaryCourseIdFromRequest();
        $stepUrlParameters = $evaluationPrimaryCourseId !== null && $evaluationService->isEvaluationCourse($course)
            ? ['primaryCourse' => $evaluationPrimaryCourseId]
            : [];

        /** @var array<NavigationGroup> $navigationGroups */
        $navigationGroups = $course->lessons->map(function (Lesson $lesson) use ($stepUrlParameters): NavigationGroup {
            return NavigationGroup::make($lesson->name)
                ->collapsed(fn (): bool => ! $lesson->isActive())
                ->items($lesson->steps->map(function (StepModel $step) use ($stepUrlParameters): NavigationItem {
                    return NavigationItem::make($step->name)
                        ->icon(fn (): string => $step->completed_at ? 'heroicon-o-check-circle' : '')
                        ->isActiveWhen(fn (): bool => $step->isActive())
                        ->url(function () use ($step, $stepUrlParameters): string {
                            $user = auth()->user();
                            if (! $user instanceof FilamentLmsUserInterface) {
                                return '';
                            }

                            return $user->canAccessStep($step) ? Step::getUrlForStep($step, $stepUrlParameters) : '';
                        });
                })->all());
        })->all();

        $navigationGroups[] = NavigationGroup::make('Course Completed')
            ->collapsed(fn (): bool => ! request()->routeIs(CourseCompleted::getRouteName()))
            ->collapsible(true)
            ->items([
                NavigationItem::make('Certificate')
                    ->icon('heroicon-o-trophy')
                    ->url(fn (): string => CourseCompleted::getUrl([$course->slug]))
                    ->isActiveWhen(fn (): bool => request()->routeIs(CourseCompleted::getRouteName())),
            ]);

        return $navigationGroups;
    }

    public static function currentCourseSlug(): ?string
    {
        $courseSlug = Route::current()?->parameter('courseSlug')
            ?? request()->route('courseSlug')
            ?? request()->route()?->parameter('courseSlug');

        return is_string($courseSlug) && $courseSlug !== '' ? $courseSlug : null;
    }

    public static function currentCourse(): ?Course
    {
        $courseSlug = self::currentCourseSlug();

        if ($courseSlug === null) {
            return null;
        }

        return Course::query()->where('slug', $courseSlug)->first();
    }
}
