<?php

use App\Http\Controllers\EntryApiController;
use Illuminate\Support\Facades\Route;

/*
 * Import API. Authenticated with the site's manage token as a bearer token.
 * POST /api/v1/sites/{slug}/entries  — import one entry or a batch.
 */
Route::post('/v1/sites/{slug}/entries', [EntryApiController::class, 'store'])
    ->where('slug', '[a-z0-9-]{1,72}')
    ->middleware('throttle:120,1')
    ->name('api.entries.store');
