<?php

use App\Apps\CmsproPolling\Controllers\User\ViewController;
use Illuminate\Support\Facades\Route;

// 我的投票（我发起的）
Route::get('/', [ViewController::class, 'index']);

// 发起投票
Route::get('/create', [ViewController::class, 'create']);

// 编辑投票
Route::get('/edit/{id}', [ViewController::class, 'edit']);

// 我的参与记录
Route::get('/records', [ViewController::class, 'records']);
