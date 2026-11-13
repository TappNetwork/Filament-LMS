<?php

namespace Tapp\FilamentLms;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Tapp\FilamentLms\Concerns\HasTopbarNavigation;
use Tapp\FilamentLms\Pages\CourseCompleted;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step;
use Tapp\FilamentLms\Support\CourseStepNavigation;
use Tapp\FilamentLms\Support\LmsPrimaryNavigation;

class LmsPanelProvider extends PanelProvider
{
    use HasTopbarNavigation;

    public function panel(Panel $panel): Panel
    {
        if (config('filament-lms.show_exit_lms_link')) {
            $panel->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament-lms::components.exit-lms'),
            );
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_LOGO_AFTER,
            fn (): ?View => $this->renderCourseTopbarNavigation(),
        );

        if (config('filament-lms.vite_theme')) {
            $panel->viteTheme(config('filament-lms.vite_theme'));
        }

        // Enable top navigation for the dashboard (only affects non-course pages)
        if (config('filament-lms.top_navigation')) {
            $panel->topNavigation();
        }

        // Disable top navigation dynamically when on a course page
        // This needs to happen before the layout is rendered
        Filament::serving(function () {
            if (Filament::getCurrentOrDefaultPanel()->getId() !== 'lms') {
                return;
            }

            // NavigationManager is a container singleton constructed for whichever
            // panel was current first. Drop a stale app/admin instance so LMS
            // getNavigation() does not evaluate foreign Resource::getUrl() closures.
            app()->forgetInstance(NavigationManager::class);

            if (CourseStepNavigation::currentCourseSlug() !== null) {
                $panel = Filament::getPanel('lms');
                $panel->topNavigation(false);
            }
        });

        if (config('filament-lms.brand_logo')) {
            $panel->brandLogo(asset(config('filament-lms.brand_logo')));

            if (config('filament-lms.brand_logo_height')) {
                $panel->brandLogoHeight(config('filament-lms.brand_logo_height'));
            }
        } else {
            $panel->brandName(config('filament-lms.brand_name'));
        }

        $panel = $panel
            ->id('lms')
            ->path('lms')
            ->homeUrl(config('filament-lms.home_url'))
            ->font(config('filament-lms.font'))
            ->darkMode(false)
            ->login();

        if (config('filament-lms.sidebar_collapsible_on_desktop')) {
            $panel->sidebarCollapsibleOnDesktop();
        }

        // Add tenancy support if enabled
        if (config('filament-lms.tenancy.enabled')) {
            $tenantModel = config('filament-lms.tenancy.model');
            if ($tenantModel) {
                // Use the configured slug attribute for tenant URL routing
                $slugAttribute = config('filament-lms.tenancy.slug_attribute', 'slug');
                $panel->tenant($tenantModel, slugAttribute: $slugAttribute);
            }
        }

        return $panel
            // ->renderHook(
                // TODO how can we configure this
            //     PanelsRenderHook::BODY_END,
            //     fn (): View => view('usersnap'),
            // )
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return LmsPrimaryNavigation::apply($builder);
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
                AccountWidget::class,
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                $this->csrfMiddleware(),
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function csrfMiddleware(): string
    {
        if (class_exists(PreventRequestForgery::class)) {
            return PreventRequestForgery::class;
        }

        return 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken';
    }

    private function renderCourseTopbarNavigation(): ?View
    {
        if (Filament::getCurrentOrDefaultPanel()->getId() !== 'lms') {
            return null;
        }

        if (CourseStepNavigation::currentCourseSlug() === null) {
            return null;
        }

        $topNavigation = LmsPrimaryNavigation::topbarItems();

        if ($topNavigation === []) {
            return null;
        }

        $navigation = $this->buildTopbarNavigation($topNavigation, collect());

        return view('filament-lms::components.topbar-navigation', ['navigation' => $navigation]);
    }
}
