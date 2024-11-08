<?php

namespace Tapp\FilamentLms;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Step;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Components\CourseLayout;

class LmsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('lms')
            ->path('lms')
            ->topNavigation()
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder->items([
                    NavigationItem::make('Home')
                        ->icon('heroicon-o-home')
                        ->url(fn (): string => '/'),
                    NavigationItem::make('Courses')
                        ->icon('heroicon-o-academic-cap')
                        ->url(fn (): string => '/lms'),
                    ]);
                })
            ->colors([
                'primary' => Color::Emerald,
            ])
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
            ->livewireComponents([
                CourseLayout::class,
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
}
