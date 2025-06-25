<?php

namespace Tapp\FilamentLms;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Tapp\FilamentLms\Concerns\HasTopbarNavigation;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step;

class LmsPanelProvider extends PanelProvider
{
    use HasTopbarNavigation;

    public function panel(Panel $panel): Panel
    {
        if (config('filament-lms.show_exit_lms_link')) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function () {
                    if (Filament::getCurrentPanel()->getId() == 'lms') {
                        return view('filament-lms::components.exit-lms');
                    }
                }
            );
        }

        if (config('filament-lms.vite_theme')) {
            $panel->viteTheme(config('filament-lms.vite_theme'));
        }

        if (config('filament-lms.top_navigation')) {
            $panel->topNavigation();
        }

        if (config('filament-lms.brand_logo')) {
            $panel->brandLogo(asset(config('filament-lms.brand_logo')));

            if (config('filament-lms.brand_logo_height')) {
                $panel->brandLogoHeight(config('filament-lms.brand_logo_height'));
            }
        } else {
            $panel->brandName(config('filament-lms.brand_name'));
        }

        return $panel
            ->id('lms')
            ->path('lms')
            ->homeUrl(config('filament-lms.home_url'))
            ->font(config('filament-lms.font'))
            ->darkMode(false)
            // ->renderHook(
                // TODO how can we configure this
            //     PanelsRenderHook::BODY_END,
            //     fn (): View => view('usersnap'),
            // )
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $this->navigationItems($builder);
            })
            ->colors(config('filament-lms.colors', []))
            ->discoverResources(in: app_path('Filament/Lms/Resources'), for: 'App\\Filament\\Lms\\Resources')
            ->discoverPages(in: app_path('Filament/Lms/Pages'), for: 'App\\Filament\\Lms\\Pages')
            ->pages([
                Dashboard::class,
                Step::class,
                CourseCompleted::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Lms/Widgets'), for: 'App\\Filament\\Lms\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function navigationItems(NavigationBuilder $builder): NavigationBuilder
    {
        $extraNavigationItems = config('filament-lms.extra_navigation_items');

        if (Route::current()->parameter('courseSlug')) {
            filament()->getCurrentPanel()->topNavigation(false);

            FilamentView::registerRenderHook(
                PanelsRenderHook::TOPBAR_START,
                function () use ($extraNavigationItems): View {
                    $topNavigation = [
                        ...$extraNavigationItems,
                        NavigationItem::make('Courses')
                            ->icon('heroicon-o-academic-cap')
                            ->isActiveWhen(fn (): bool => request()->routeIs(Dashboard::getRouteName()))
                            ->url(fn (): string => Dashboard::getUrl()),
                    ];

                    $groups = collect();

                    $navigation = $this->buildTopbarNavigation($topNavigation, $groups);

                    return view('filament-lms::components.topbar-navigation', ['navigation' => $navigation]);
                },
            );

            $course = Course::where('slug', Route::current()->parameter('courseSlug'))->firstOrFail();

            $navigationGroups = $course->lessons->map(function ($lesson) {
                /** @var \Tapp\FilamentLms\Models\Lesson $lesson */
                return NavigationGroup::make($lesson->name)
                    // TODO collapsed is not working
                    // ->collapsed(fn (): bool => ! $lesson->isActive())
                    // ->collapsible(true)
                    ->items($lesson->steps->map(function ($step) {
                        /** @var \Tapp\FilamentLms\Models\Step $step */
                        return NavigationItem::make($step->name)
                            ->icon(fn (): string => $step->completed_at ? 'heroicon-o-check-circle' : '')
                            ->isActiveWhen(fn (): bool => $step->isActive())
                            ->url(fn (): string => $step->available ? $step->url : '');
                    })->toArray());
            })->toArray();

            $navigationGroups[] = NavigationGroup::make('Course Completed')->items([
                NavigationItem::make('Certificate')
                    ->icon('heroicon-o-trophy')
                    ->url(fn (): string => CourseCompleted::getUrl([$course->slug]))
                    ->isActiveWhen(fn (): bool => request()->routeIs(CourseCompleted::getRouteName())),
            ]);

            $builder->groups($navigationGroups);

            return $builder;
        }

        return $builder->items([
            ...$extraNavigationItems,
            NavigationItem::make('Courses')
                ->icon('heroicon-o-academic-cap')
                ->isActiveWhen(fn (): bool => request()->routeIs(Dashboard::getRouteName()))
                ->url(fn (): string => Dashboard::getUrl()),
        ]);
    }
}
