<?php

namespace Tapp\FilamentLms;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Resources\Resource;
use InvalidArgumentException;
use Tapp\FilamentFormBuilder\FilamentFormBuilderPlugin;
use Tapp\FilamentLms\Pages\CreateRubric;
use Tapp\FilamentLms\Pages\Reporting;
use Tapp\FilamentLms\Pages\ViewRubric;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\CreditCategoryResource;
use Tapp\FilamentLms\Resources\DocumentResource;
use Tapp\FilamentLms\Resources\ImageResource;
use Tapp\FilamentLms\Resources\LessonResource;
use Tapp\FilamentLms\Resources\LinkResource;
use Tapp\FilamentLms\Resources\StepResource;
use Tapp\FilamentLms\Resources\TestResource;
use Tapp\FilamentLms\Resources\VideoResource;

class Lms implements Plugin
{
    public function getId(): string
    {
        return 'lms';
    }

    public function register(Panel $panel): void
    {
        $panel->resources(self::registeredResourceClasses());

        $panel->pages([
            Reporting::class,
            CreateRubric::class,
            ViewRubric::class,
        ]);

        // Register the form builder plugin
        $panel->plugin(FilamentFormBuilderPlugin::make());
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

    /**
     * Filament resource classes registered by this plugin, after merging `filament-lms.resources` overrides.
     *
     * @return list<class-string<resource>>
     */
    public static function registeredResourceClasses(): array
    {
        $defaults = [
            'CourseResource' => CourseResource::class,
            'LessonResource' => LessonResource::class,
            'StepResource' => StepResource::class,
            'VideoResource' => VideoResource::class,
            'DocumentResource' => DocumentResource::class,
            'LinkResource' => LinkResource::class,
            'TestResource' => TestResource::class,
            'ImageResource' => ImageResource::class,
            'CreditCategoryResource' => CreditCategoryResource::class,
        ];

        /** @var array<string, mixed> $overrides */
        $overrides = config('filament-lms.resources', []);

        $classes = [];

        foreach ($defaults as $key => $defaultClass) {
            if ($key === 'CreditCategoryResource' && ! config('filament-lms.credits_enabled', false)) {
                continue;
            }

            $class = array_key_exists($key, $overrides) ? $overrides[$key] : $defaultClass;

            if (! is_string($class) || $class === '') {
                throw new InvalidArgumentException("filament-lms.resources.{$key} must be a non-empty class-string.");
            }

            if (! class_exists($class)) {
                throw new InvalidArgumentException("filament-lms.resources.{$key} class [{$class}] does not exist.");
            }

            if (! is_subclass_of($class, Resource::class)) {
                throw new InvalidArgumentException("filament-lms.resources.{$key} class [{$class}] must extend ".Resource::class.'.');
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
