<?php

use Illuminate\Support\Facades\Route;

// Single-page app shell. The Vue Router (resources/js/router.ts) owns all
// client-side routing; every non-API, non-asset path falls through to the
// same Blade view so deep links (e.g. /purchase-orders/3) work on refresh.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
