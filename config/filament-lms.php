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

    // Media URL configuration
    'media' => [
        // If true, generates signed URLs for private storage (S3, etc.)
        'use_signed_urls' => false,
        // Default expiration time for signed URLs in minutes (default: 60 minutes)
        'signed_url_expiration' => 60,
    ],
];
