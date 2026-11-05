<?php

use App\Apps\CmsproPolling\Controllers\Admin\ActivityController;
use App\Apps\CmsproPolling\Controllers\Admin\RecordController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // 活动管理
    Route::prefix('activities')->controller(ActivityController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('export/{id}', 'exportStats');
        Route::get('{id}', 'show');
        Route::put('{id}', 'update');
        Route::delete('{id}', 'destroy');
        Route::put('{id}/status', 'updateStatus');
        Route::get('{id}/stats', 'stats');
    });

    // 投票记录
    Route::prefix('records')->controller(RecordController::class)->group(function () {
        Route::get('/', 'index');
    });
});
