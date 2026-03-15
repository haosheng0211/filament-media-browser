<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The disk and root directory used by the media browser.
    | Files are managed directly via Laravel's Storage facade — no database.
    |
    */
    'disk' => 'public',

    'directory' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'accepted_file_types' => ['image/*', 'video/*', 'audio/*'],

    'max_file_size' => 10240, // KB

];
