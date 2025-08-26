<?php

namespace Tapp\FilamentLms\Concerns;

use Filament\Panel;

trait HasLmsSlug
{
    public static function getSlug(?Panel $panel = null): string
    {
        return 'lms/'.parent::getSlug($panel);
    }
}
