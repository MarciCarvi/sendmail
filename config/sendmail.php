<?php

return [

    'table_prefix' => env('SM_TABLE_PREFIX', 'sm_'),

    'version' => function_exists('file_get_contents') && file_exists(base_path('VERSION'))
        ? trim(file_get_contents(base_path('VERSION')))
        : '1.0.0',

    'release_date' => function_exists('file_get_contents') && file_exists(base_path('RELEASE_DATE'))
        ? trim(file_get_contents(base_path('RELEASE_DATE')))
        : null,

    'license' => [
        // Endpoint del license server (firma le risposte con RSA). Nessun PAT
        // lato client: la validazione avviene server-side su carvisiglia.com.
        'api'        => env('SM_LICENSE_API', 'https://carvisiglia.com/sendmail-license/check.php'),

        // Chiave pubblica RSA per verificare la firma delle risposte. È pubblica
        // per definizione → può stare nel codice. La privata resta sul server.
        'public_key' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqfL9Zit8MogsJr3CGkZ2
bh7xE2Olbe7Jq56VkscIlhLyo7a3DQZjlHiCjND7NgcNskNKY0DbmXp+j80LqGQc
1qxWgoio75XGQKU758aRwO8TpYIrh+EkT7yAqd+cS9CbJ1GdnXPJAepmJgeMQKuY
2FRUnh+20kp5OwWww2im/Hd2I25n9/RELMnavc6oAnD5PHtkfYt6wDsLNwroSGey
JnQ/FWlI2r65xAyabTjq1NXQLDDGUwqObQfn9a+GhvdiP0xQj8Kq/CzerL1Hu9Uu
IkeJW6JlYRpk3Y2vnpX8TDzlPXxyod6hurldTV4b5zg+7MzpIPf43aGAQ7hATnrq
/QIDAQAB
-----END PUBLIC KEY-----
PEM,

        'grace_days' => 3,
    ],

    'updates' => [
        'repo'                 => env('SM_RELEASES_REPO', 'marcellocarvisiglia/sendmail'),
        'check_interval_hours' => 24,
    ],

];
