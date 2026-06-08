<?php

use App\Models\User;

return [
    'theme' => 'default',
    'font' => 'Poppins',
    'home_url' => '/lms',
    'brand_name' => 'LMS',
    'brand_logo' => '',
    'brand_logo_height' => null,

    // Falls back to brand_logo if not set. Recommended max height: 90px for optimal certificate layout
    'certificate_logo' => '',

    // Show signature lines on certificates
    'certificate_show_signatures' => true,

    // Show unique certificate ID
    'certificate_show_id' => true,

    'vite_theme' => '',
    'colors' => [],
    'awards' => [
        'default' => 'Default',
    ],
    // Enable top navigation on the LMS dashboard (courses list page).
    // Note: This only affects the dashboard. Course pages always use sidebar navigation.
    'top_navigation' => false,
    'show_exit_lms_link' => true,
    'exit_lms_label' => 'Exit LMS',

    // If true, users only see courses they are assigned to via lms_course_user. If false, all courses are visible.
    'restrict_course_visibility' => false,

    // User model class for course assignments
    // @phpstan-ignore-next-line - User model is defined in consuming application
    'user_model' => User::class,

    // User search columns for relation managers
    'user_search_columns' => [
        'first_name',
        'last_name',
        'email',
    ],

    // Course search columns for relation managers
    'course_search_columns' => [
        'name',
        'external_id',
    ],

    // User name columns for the course progress report. Use ['first_name', 'last_name'] when the
    // users table has first_name and last_name, or ['name'] when it has a single name column.
    'report_user_name_columns' => ['first_name', 'last_name'],

    // Media URL configuration
    'media' => [
        // If true, generates signed URLs for private storage (S3, etc.)
        'use_signed_urls' => false,
        // Default expiration time for signed URLs in minutes (default: 60 minutes)
        'signed_url_expiration' => 60,
    ],

    // Credits configuration
    'credits_enabled' => false,
    'credits_label' => 'Credits',
    'credits_repeater_label' => 'Credits',
    'credits_add_action_label' => 'Add credits',

    // Multi-Tenancy configuration
    'tenancy' => [
        // Enable tenancy support
        // When enabled, LMS URLs will be scoped to tenants: /lms/{tenant}/...
        // Example: /lms/acme-corp/courses, /lms/acme-corp/certificates/...
        //
        // When enabled, a global scope automatically filters all LMS queries
        // (Course, Lesson, Step, Document, Video, Image, Link, Test, StepUser) to the
        // current tenant. This prevents cross-tenant data access.
        //
        // This uses a model-level scope (not Filament's Resource-level scope) for:
        // - Consistent filtering across both admin Resources and LMS Pages
        // - Better performance (single scope, no runtime checks)
        // - Simpler architecture
        //
        // To bypass tenant filtering (e.g., for admin operations), use:
        // - Course::withoutTenantScope()->get() - Access all tenants
        // - Course::forTenant($tenantId)->get() - Access specific tenant
        'enabled' => false,

        // The Tenant model class (e.g., App\Models\Team::class, App\Models\Organization::class)
        'model' => null,

        // The tenant relationship name (defaults to snake_case of tenant model class name)
        // For example: Team::class -> 'team', Organization::class -> 'organization'
        // This should match what you configure in your Filament Panel:
        // ->tenantOwnershipRelationshipName('team')
        'relationship_name' => null,

        // The tenant column name (defaults to snake_case of tenant model class name + '_id')
        // You can override this if needed
        'column' => null,

        // The attribute to use for URL routing (slugAttribute)
        // Common values: 'slug', 'id', 'uuid'
        // Defaults to 'slug' if not specified
        // This should match your tenant model's route key name (getRouteKeyName())
        'slug_attribute' => 'slug',

        // Permission checking: Your User model must implement:
        // 1. FilamentUser contract with canAccessPanel(Panel $panel): bool
        //    - This controls whether the user can access the LMS panel at all
        // 2. HasTenants contract with these methods:
        //    - getTenants(Panel $panel): Collection - Returns all tenants the user can access
        //    - canAccessTenant(Model $tenant): bool - Checks if user can access a specific tenant
        //
        // Example implementation:
        // public function getTenants(Panel $panel): Collection {
        //     return $this->teams; // Returns user's teams
        // }
        // public function canAccessTenant(Model $tenant): bool {
        //     return $this->teams()->whereKey($tenant)->exists();
        // }
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugin integrations
    |--------------------------------------------------------------------------
    |
    | Optional integrations with other Filament packages.
    |
    */
    'integrations' => [
        'filament_library' => [
            // When true (and tapp/filament-library is installed), Step materials may reference Library items.
            'enabled' => false,

            // Max rows returned when searching library files or links on the Step form.
            'material_select_limit' => 200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament resource class overrides
    |--------------------------------------------------------------------------
    |
    | Optional map of short keys to custom Filament Resource classes
    | Only keys listed below are recognized; each value must
    | be a class extending Filament\Resources\Resource.
    |
    | Example (in your app's config/filament-lms.php):
    |
    | 'resources' => [
    |     'CourseResource' => \App\Filament\Resources\Lms\CourseResource::class,
    | ],
    |
    | Keys: CourseResource, LessonResource, StepResource, VideoResource, DocumentResource,
    | LinkResource, TestResource, ImageResource, CreditCategoryResource (when credits_enabled).
    |
    */
    'resources' => [],

    /*
    |--------------------------------------------------------------------------
    | Common Cartridge / SCORM import
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Embedded player completion guards
    |--------------------------------------------------------------------------
    */
    'embedded_player_min_session_seconds' => 90,
    'embedded_player_min_session_seconds_html5' => 300,

    'common_cartridge_import' => [
        'delete_after_success' => true,
        'storage_disk' => 'local',
        'storage_directory' => 'filament-lms/cartridge-imports',
        'default_import_path' => null,
        'retain_extracted_packages' => true,
        'packages_directory' => 'lms-scorm-packages',
    ],

    // Optional path to Node binary for Articulate slide JSON extraction
    'node_binary' => null,
];
