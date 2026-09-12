<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ApiController;

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::post('/transactions', [ApiController::class, 'store']);
});
