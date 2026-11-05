<?php

use App\Apps\CmsproPolling\Controllers\Admin\ViewController;
use Illuminate\Support\Facades\Route;

// 活动管理页面
Route::get('/activities', [ViewController::class, 'activityIndex']);
Route::get('/activities/form', [ViewController::class, 'activityForm']);
Route::get('/activities/form/{id}', [ViewController::class, 'activityForm']);
Route::get('/activities/stats/{id}', [ViewController::class, 'activityStats']);

// 投票记录页面
Route::get('/records', [ViewController::class, 'recordIndex']);
