<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Concerns;

use Laravel\Mcp\Server\Tool;
use ReflectionClass;

trait HasSanctumAbilities
{
    /**
     * Sanctum ability keys mapped to Filament checkbox labels.
     *
     * @return array<string, string>
     */
    public static function sanctumAbilities(): array
    {
        /** @var list<class-string<Tool>> $toolClasses */
        $toolClasses = (new ReflectionClass(static::class))->getDefaultProperties()['tools'] ?? [];

        $abilities = [];

        foreach ($toolClasses as $toolClass) {
            $tool = new $toolClass;
            $abilities[$tool->name()] = $tool->title();
        }

        return $abilities;
    }
}
