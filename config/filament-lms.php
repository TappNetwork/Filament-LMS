<?php

use Filament\Navigation\NavigationItem;

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
    'extra_navigation_items' => [
        NavigationItem::make('Home')
            ->icon('heroicon-o-home')
            ->url(fn (): string => '/'),
    ],
    // If true, users only see courses they are assigned to via lms_course_user. If false, all courses are visible.
    'restrict_course_visibility' => false,
];
