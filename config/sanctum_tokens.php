<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API access token (Bearer) lifetime
    |--------------------------------------------------------------------------
    |
    | Short-lived token used on Authorization: Bearer for normal API calls.
    |
    */

    'access_expires_minutes' => (int) env('SANCTUM_ACCESS_TOKEN_EXPIRATION', 60),

    /*
    |--------------------------------------------------------------------------
    | Refresh token lifetime
    |--------------------------------------------------------------------------
    |
    | Long-lived token used only with POST /auth/refresh to obtain a new pair.
    |
    */

    'refresh_expires_days' => (int) env('SANCTUM_REFRESH_TOKEN_EXPIRATION', 30),

];
