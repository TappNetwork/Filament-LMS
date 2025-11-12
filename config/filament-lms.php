<?php

return [
    'theme' => 'default',
    'font' => 'Poppins',
    'home_url' => '/lms',
    'brand_name' => 'LMS',
    'brand_logo' => '',
    'brand_logo_height' => null,
    'vite_theme' => '',
    'colors' => [],
    'awards' => [
        'Default' => 'default',
    ],
    'top_navigation' => false,
    'show_exit_lms_link' => true,

    // If true, users only see courses they are assigned to via lms_course_user. If false, all courses are visible.
    'restrict_course_visibility' => false,

    // User model class for course assignments
    // @phpstan-ignore-next-line - User model is defined in consuming application
    'user_model' => \App\Models\User::class,

    // User search columns for relation managers
    'user_search_columns' => [
        'first_name',
        'last_name',
        'email',
    ],

    // Media URL configuration
    'media' => [
        // If true, generates signed URLs for private storage (S3, etc.)
        'use_signed_urls' => false,
        // Default expiration time for signed URLs in minutes (default: 60 minutes)
        'signed_url_expiration' => 60,
    ],

    // Multi-Tenancy configuration
    'tenancy' => [
        // Enable tenancy support
        // When enabled, LMS URLs will be scoped to tenants: /lms/{tenant}/...
        // Example: /lms/acme-corp/courses, /lms/acme-corp/certificates/...
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
];
