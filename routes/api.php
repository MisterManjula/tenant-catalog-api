<?php

use App\Http\Controllers\ProductController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenant::class])->group(function () {
    Route::apiResource('products', ProductController::class)->except(['destroy']);
});
