<?php

namespace Tapp\FilamentLms\Concerns;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Tapp\FilamentLms\Enums\CourseContext;
use Tapp\FilamentLms\Pages\Step as StepPage;

trait CourseLayout
{
    public function registerCourseLayout(): void
    {
        if (! $this->course->isEmbeddedPlayer()) {
            $courseNameHook = CourseContext::fromConfig()->usesSubNavigation()
                ? PanelsRenderHook::PAGE_SUB_NAVIGATION_SIDEBAR_BEFORE
                : PanelsRenderHook::SIDEBAR_NAV_START;

            FilamentView::registerRenderHook(
                $courseNameHook,
                fn (): View => view('filament-lms::components.nav-course-name', ['course' => $this->course]),
            );
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_AFTER,
            fn (): View => view('filament-lms::components.topbar-course-progress', ['course' => $this->course]),
        );

        if ($this->course->isEmbeddedPlayer() && $this instanceof StepPage) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament-lms::components.embedded-player-body-class'),
            );

            if ($this->shouldRegisterScormBridge()) {
                FilamentView::registerRenderHook(
                    PanelsRenderHook::BODY_END,
                    fn (): View => view('filament-lms::components.scorm-api-bridge', [
                        'course' => $this->course,
                        'commitUrl' => route('filament-lms.scorm-commit.store', ['course' => $this->course]),
                    ]),
                );
            }

            if ($this->shouldRegisterHtml5Bridge()) {
                FilamentView::registerRenderHook(
                    PanelsRenderHook::BODY_END,
                    fn (): View => view('filament-lms::components.html5-player-bridge', [
                        'course' => $this->course,
                        'commitUrl' => route('filament-lms.scorm-commit.store', ['course' => $this->course]),
                    ]),
                );
            }
        }
    }
}
