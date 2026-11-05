<?php

use App\Apps\CmsproPolling\Controllers\User\ActivityController;
use App\Apps\CmsproPolling\Controllers\User\RecordController;
use Illuminate\Support\Facades\Route;

// 我发起的投票
Route::prefix('activities')->controller(ActivityController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/', 'store');
    Route::get('{id}', 'show');
    Route::put('{id}', 'update');
    Route::delete('{id}', 'destroy');
    Route::put('{id}/status', 'updateStatus');
});

// 我的参与记录
Route::prefix('records')->controller(RecordController::class)->group(function () {
    Route::get('/', 'index');
});
