<?php

use App\Apps\CmsproPolling\Controllers\Home\PollingController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // 提交投票（游客可投票，活动级控制是否需要登录）
    Route::post('/vote/{id}', [PollingController::class, 'vote']);

    // 获取活动实时统计
    Route::get('/stats/{id}', [PollingController::class, 'stats']);
});
