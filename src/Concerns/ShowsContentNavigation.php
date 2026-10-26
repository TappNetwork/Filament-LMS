<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Concerns;

use Tapp\FilamentLms\Support\CourseEditor;

trait ShowsContentNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        if (! CourseEditor::showContentResources()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }
}
