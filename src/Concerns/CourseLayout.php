<?php

namespace Tapp\FilamentLms\Concerns;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

trait CourseLayout
{
    public function registerCourseLayout()
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): View => view('filament-lms::components.nav-course-name', ['course' => $this->course]),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_AFTER,
            fn (): View => view('filament-lms::components.topbar-course-progress', ['course' => $this->course]),
        );

    }
}
