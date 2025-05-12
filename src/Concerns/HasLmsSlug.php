<?php

namespace Tapp\FilamentLms\Concerns;

use Filament\Resources\Resource;

trait HasLmsSlug
{
    public static function getSlug(): string
    {
        return 'lms/' . parent::getSlug();
    }
} 