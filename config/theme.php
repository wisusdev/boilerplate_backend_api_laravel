<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Themes Path
    |--------------------------------------------------------------------------
    |
    | This is the base path where your themes will be stored. By default,
    | themes are stored in the "themes" directory at the root of your
    | application.
    |
    */

    'path' => base_path('themes'),

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | This is the theme that will be used when no theme is explicitly set.
    | This should match the directory name of your default theme.
    |
    */

    'default' => env('THEME_DEFAULT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Admin Theme
    |--------------------------------------------------------------------------
    |
    | This is the theme used for the admin panel. It's separate from the
    | front-end theme to allow different styling for the admin area.
    |
    */

    'admin' => env('THEME_ADMIN', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Theme Cache
    |--------------------------------------------------------------------------
    |
    | When enabled, theme configurations will be cached for better performance.
    | Disable this during development to see changes immediately.
    |
    */

    'cache' => env('THEME_CACHE', false),

    /*
    |--------------------------------------------------------------------------
    | Views Directory
    |--------------------------------------------------------------------------
    |
    | The name of the directory within each theme that contains the Blade
    | views. This is relative to the theme's root directory.
    |
    */

    'views_dir' => 'views',

    /*
    |--------------------------------------------------------------------------
    | Assets Directory
    |--------------------------------------------------------------------------
    |
    | The name of the directory within each theme that contains static assets
    | like CSS, JavaScript, and images.
    |
    */

    'assets_dir' => 'assets',

    /*
    |--------------------------------------------------------------------------
    | Public Assets Directory
    |--------------------------------------------------------------------------
    |
    | The directory in your public folder where compiled theme assets will
    | be published. Assets are published here for web access.
    |
    */

    'public_assets_dir' => 'themes',

    /*
    |--------------------------------------------------------------------------
    | Theme Configuration File
    |--------------------------------------------------------------------------
    |
    | The name of the JSON file that contains the theme's metadata and
    | configuration. This file should be in the root of each theme.
    |
    */

    'config_file' => 'theme.json',

    /*
    |--------------------------------------------------------------------------
    | Parent Theme Support
    |--------------------------------------------------------------------------
    |
    | Enable parent/child theme relationships similar to WordPress. When
    | enabled, views not found in the child theme will fallback to the parent.
    |
    */

    'parent_theme_support' => true,

    /*
    |--------------------------------------------------------------------------
    | Theme Locations
    |--------------------------------------------------------------------------
    |
    | Define the available theme locations/slots. These are used by the
    | menu system and widgets to know where content can be placed.
    |
    */

    'locations' => [
        'header' => 'Header Navigation',
        'footer' => 'Footer Navigation',
        'sidebar' => 'Sidebar',
        'primary' => 'Primary Menu',
        'social' => 'Social Links',
    ],

];
