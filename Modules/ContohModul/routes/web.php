<?php

use Illuminate\Support\Facades\Route;
use Modules\ContohModul\Http\Controllers\ContohModulController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contohmoduls', ContohModulController::class)->names('contohmodul');
});
