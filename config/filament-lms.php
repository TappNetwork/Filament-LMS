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
    // Enable top navigation on the LMS dashboard (courses list page).
    // Note: This only affects the dashboard. Course pages always use sidebar navigation.
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
];
