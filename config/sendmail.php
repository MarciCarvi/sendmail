<?php

return [

    'table_prefix' => env('SM_TABLE_PREFIX', 'sm_'),

    'version' => function_exists('file_get_contents') && file_exists(base_path('VERSION'))
        ? trim(file_get_contents(base_path('VERSION')))
        : '1.0.0',

    'license' => [
        'pat'        => env('SM_LICENSE_PAT', ''),
        'repo'       => env('SM_LICENSES_REPO', 'marcellocarvisiglia/sendmail-licenses'),
        'file'       => env('SM_LICENSES_FILE', 'licenses.json'),
        'grace_days' => 3,
    ],

    'updates' => [
        'repo'                 => env('SM_RELEASES_REPO', 'marcellocarvisiglia/sendmail'),
        'check_interval_hours' => 24,
    ],

];
