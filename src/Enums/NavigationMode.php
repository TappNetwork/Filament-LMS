<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Enums;

enum NavigationMode: string
{
    case Native = 'native';
    case MirrorPanel = 'mirror_panel';

    public static function fromConfig(): self
    {
        $value = config('filament-lms.navigation.mode', self::Native->value);

        return self::tryFrom(is_string($value) ? $value : self::Native->value) ?? self::Native;
    }

    public function isMirrorPanel(): bool
    {
        return $this === self::MirrorPanel;
    }
}
