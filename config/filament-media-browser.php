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

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    |
    | When true, selected files store the full URL via Storage::url().
    | When false, selected files store the relative path (e.g. "media/photo.jpg").
    | This can be overridden per field via ->storeAsUrl() or ->storePath().
    |
    */
    'store_as_url' => true,

];
