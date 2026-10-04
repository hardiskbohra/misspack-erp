<?php

/*
|--------------------------------------------------------------------------
| Brand
|--------------------------------------------------------------------------
| One place for the company's identity, so the mark, the invoice, the public
| pages and the portal all say the same thing. Values can be overridden per
| install in .env without touching a view.
*/

return [

    'name' => env('BRAND_NAME', 'MissPack'),

    'tagline' => env('BRAND_TAGLINE', 'Packed Perfect'),

    'website' => env('BRAND_WEBSITE', 'www.themisspack.com'),

    'website_url' => env('BRAND_WEBSITE_URL', 'https://www.themisspack.com'),

    'instagram' => env('BRAND_INSTAGRAM', 'https://www.instagram.com/themisspack/'),

    'instagram_handle' => env('BRAND_INSTAGRAM_HANDLE', '@themisspack'),

    /*
     | Two marks ship with the app: the black wordmark for white paper and
     | light cards, and the white wordmark for dark surfaces. Stickers, print
     | documents and light pages use `logo_print`; dark headers and public
     | pages on a dark background use `logo_dark`.
     */
    'logo_print' => 'images/logo-dark.png',

    'logo_dark' => 'images/logo.png',

];
