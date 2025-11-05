<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SEC API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SEC (Securities and Exchange Commission) API requests.
    | The SEC requires a User-Agent header with contact information for API
    | requests. Set your contact email in your .env file.
    |
    */

    'api_contact' => env('SEC_API_CONTACT', 'info@caitong.com'),

    /*
    |--------------------------------------------------------------------------
    | SEC API Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('SEC_API_BASE_URL', 'https://www.sec.gov'),

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    */
    'timeout' => env('SEC_API_TIMEOUT', 30),

];

