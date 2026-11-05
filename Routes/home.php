<?php

use App\Apps\CmsproPolling\Controllers\Home\PollingController;
use Illuminate\Support\Facades\Route;

// 投票列表
Route::get('/', [PollingController::class, 'index']);

// 投票详情
Route::get('/{id}', [PollingController::class, 'detail'])->whereNumber('id');
