<?php

use Illuminate\Support\Facades\Route;
use Modules\ContohModul\Http\Controllers\ContohModulController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('contohmoduls', ContohModulController::class)->names('contohmodul');
});
