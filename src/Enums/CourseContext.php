<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Enums;

enum CourseContext: string
{
    case ReplaceSidebar = 'replace_sidebar';
    case SubNavigation = 'sub_navigation';

    public static function fromConfig(): self
    {
        $value = config('filament-lms.navigation.course_context', self::ReplaceSidebar->value);

        return self::tryFrom(is_string($value) ? $value : self::ReplaceSidebar->value) ?? self::ReplaceSidebar;
    }

    public function usesSubNavigation(): bool
    {
        return $this === self::SubNavigation;
    }

    public function replacesSidebar(): bool
    {
        return $this === self::ReplaceSidebar;
    }
}
