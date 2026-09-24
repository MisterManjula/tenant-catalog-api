<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicationController;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenant::class])->group(function () {
    Route::apiResource('products', ProductController::class)->except(['destroy']);

    // EnsureIdempotency stores keys per tenant, so it runs after ResolveTenant.
    Route::apiResource('publications', PublicationController::class)
        ->only(['store', 'show'])
        ->middlewareFor('store', EnsureIdempotency::class);
});
