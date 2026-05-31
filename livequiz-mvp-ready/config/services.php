<?php

return [
    'livequiz_ws' => [
        'hook' => env('LIVEQUIZ_WS_HOOK', 'http://127.0.0.1:6001/broadcast'),
    ],

    'livequiz_images' => [
        'verify_ssl' => env('LIVEQUIZ_IMAGE_VERIFY_SSL', false),
    ],
];
