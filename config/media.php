<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk to use for storing uploaded files.
    |
    */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file size in kilobytes.
    |
    */
    'max_file_size' => env('MEDIA_MAX_FILE_SIZE', 10240), // 10MB

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    |
    | List of allowed MIME types for file uploads.
    |
    */
    'allowed_mime_types' => [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',

        // Videos
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',

        // Audio
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        'audio/mp3',

        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',

        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/gzip',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Sizes
    |--------------------------------------------------------------------------
    |
    | Thumbnail sizes to generate for uploaded images.
    | Format: 'name' => [width, height]
    |
    */
    'image_sizes' => [
        'thumbnail' => [150, 150],
        'medium' => [300, 300],
        'large' => [1024, 1024],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    |
    | Quality setting for generated thumbnails (1-100).
    |
    */
    'image_quality' => 85,
];
