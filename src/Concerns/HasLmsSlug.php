<?php

namespace Tapp\FilamentLms\Concerns;

trait HasLmsSlug
{
    public static function getSlug(): string
    {
        return 'lms/'.parent::getSlug();
    }
}
