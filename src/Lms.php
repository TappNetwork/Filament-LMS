<?php

namespace Tapp\FilamentLms;

use Closure;
use Devtical\Sanctum\Pages\Sanctum;
use Devtical\Sanctum\SanctumPlugin;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
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
use Tapp\FilamentLms\Support\CourseListingSort;

class Lms implements Plugin
{
    /**
     * @var (Closure(Builder, string): Builder)|string
     */
    protected Closure|string $courseSort = 'name';

    protected string $courseSortDirection = 'asc';

    public function getId(): string
    {
        return 'lms';
    }

    /**
     * Order for the learner course catalog and the admin course list.
     *
     * Pass a column name (`name`, `created_at`) or a closure that receives the query and direction.
     * The default is alphabetical by course name. Use `created_at` desc to restore newest-first.
     *
     * The learner catalog reads this even though that panel does not register the plugin.
     * Call it on the admin `Lms::make()` instance.
     *
     * @param  (Closure(Builder, string): Builder)|string  $sort
     */
    public function courseSort(Closure|string $sort, string $direction = 'asc'): static
    {
        if (is_string($sort) && trim($sort) === '') {
            throw new InvalidArgumentException('Course sort column cannot be empty.');
        }

        $this->courseSort = $sort;
        $this->courseSortDirection = $direction === 'desc' ? 'desc' : 'asc';

        app()->instance(CourseListingSort::class, new CourseListingSort($this->courseSort, $this->courseSortDirection));

        return $this;
    }

    public function getCourseSort(): Closure|string
    {
        return $this->courseSort;
    }

    public function getCourseSortDirection(): string
    {
        return $this->courseSortDirection;
    }

    /**
     * @return array{0: Closure|string, 1: string}
     */
    public static function courseSortConfiguration(): array
    {
        $panel = Filament::getCurrentPanel();

        if ($panel?->hasPlugin('lms')) {
            /** @var static $plugin */
            $plugin = $panel->getPlugin('lms');

            return [$plugin->courseSort, $plugin->courseSortDirection];
        }

        if (app()->bound(CourseListingSort::class)) {
            $listingSort = app(CourseListingSort::class);

            return [$listingSort->sort, $listingSort->direction];
        }

        return ['name', 'asc'];
    }

    public static function applyCourseSort(Builder $query): Builder
    {
        [$sort, $direction] = static::courseSortConfiguration();

        return static::applyCourseSortUsing($query, $sort, $direction);
    }

    public static function configureCourseTableSort(Table $table): Table
    {
        [$sort, $direction] = static::courseSortConfiguration();

        if ($sort instanceof Closure) {
            return $table->defaultSort(
                fn (Builder $query, string $direction): Builder => static::applyCourseSortUsing($query, $sort, $direction),
                $direction,
            );
        }

        return $table->defaultSort($sort, $direction);
    }

    protected static function applyCourseSortUsing(Builder $query, Closure|string $sort, string $direction): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($sort instanceof Closure) {
            $sorted = $sort($query, $direction);

            return $sorted instanceof Builder ? $sorted : $query;
        }

        return $query->orderBy($sort, $direction);
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

        if (
            config('filament-lms.sanctum_ui.enabled', true)
            && $panel->getId() === 'admin'
            && ! $panel->hasPlugin('filament-sanctum')
            && class_exists(SanctumPlugin::class)
        ) {
            $panel->plugin(SanctumPlugin::make());
        }
    }

    public function boot(Panel $panel): void
    {
        if (! $this->shouldRegisterLmsAdminSanctumMenu($panel)) {
            return;
        }

        $panel->userMenuItems([
            Action::make('sanctum')
                ->label(trans('Sanctum'))
                ->url(fn (): string => Sanctum::getUrl(panel: $panel->getId()))
                ->icon(config('filament-sanctum.navigation.icon', 'heroicon-o-finger-print'))
                ->visible(fn (): bool => $this->currentUserIsLmsAdmin()),
        ]);
    }

    protected function shouldRegisterLmsAdminSanctumMenu(Panel $panel): bool
    {
        return config('filament-lms.sanctum_ui.enabled', true)
            && config('filament-lms.sanctum_ui.authorize_lms_admin', true)
            && $panel->getId() === 'admin'
            && class_exists(SanctumPlugin::class)
            && class_exists(Sanctum::class)
            && $panel->hasPlugin('filament-sanctum');
    }

    protected function currentUserIsLmsAdmin(): bool
    {
        $user = Auth::user();

        return $user !== null
            && method_exists($user, 'isLmsAdmin')
            && $user->isLmsAdmin();
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
     * Class strings are unique so Filament does not register the same resource twice (duplicate nav, routes).
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

        return array_values(array_unique($classes));
    }
}
