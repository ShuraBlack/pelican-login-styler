<?php

// Saved by the plugin settings as LOGIN_STYLER_<KEY> in the panel's .env.
return [
    'title' => env('LOGIN_STYLER_TITLE'),
    'heading' => env('LOGIN_STYLER_HEADING'),
    'logo' => env('LOGIN_STYLER_LOGO'),
    'logo_height' => env('LOGIN_STYLER_LOGO_HEIGHT'),
    'background_color' => env('LOGIN_STYLER_BACKGROUND_COLOR'),
    'background_image' => env('LOGIN_STYLER_BACKGROUND_IMAGE'),
    'image_fit' => env('LOGIN_STYLER_IMAGE_FIT'),
    'image_position' => env('LOGIN_STYLER_IMAGE_POSITION'),
    'image_dim' => env('LOGIN_STYLER_IMAGE_DIM'),
    // URL-encoded, because the .env writer works line by line and multi-line values would corrupt it.
    'custom_css' => rawurldecode((string) env('LOGIN_STYLER_CUSTOM_CSS', '')),
];
