<?php

namespace Tapp\FilamentLms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\DocumentResource;
use Tapp\FilamentLms\Resources\LessonResource;
use Tapp\FilamentLms\Resources\LinkResource;
use Tapp\FilamentLms\Resources\StepResource;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Pages\Reporting;

class Lms implements Plugin
{
    public function getId(): string
    {
        return 'lms';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            CourseResource::class,
            LessonResource::class,
            StepResource::class,
            VideoResource::class,
            DocumentResource::class,
            LinkResource::class,
        ]);

        $panel->pages([
            Reporting::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static;
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }
}
