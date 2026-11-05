<?php

namespace App\Apps\CmsproPolling;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('app/Apps/CmsproPolling/Config/polling.php'),
            'apps.cmspro.polling'
        );
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->loadViews();
        $this->registerHooks();
    }

    protected function registerRoutes(): void
    {
        // 后台页面路由
        Route::prefix('admin/cmspro/polling')
            ->namespace('App\Apps\CmsproPolling\Controllers\Admin')
            ->middleware(['web', 'auth:admin'])
            ->group(base_path('app/Apps/CmsproPolling/Routes/admin.php'));

        // 后台 API 路由
        Route::prefix('admin/cmspro/polling')
            ->namespace('App\Apps\CmsproPolling\Controllers\Admin')
            ->middleware(['web', 'auth:admin'])
            ->group(base_path('app/Apps/CmsproPolling/Routes/admin_api.php'));

        // 用户端页面路由
        Route::prefix('user/cmspro/polling')
            ->namespace('App\Apps\CmsproPolling\Controllers\User')
            ->middleware(['web', 'auth:web'])
            ->group(base_path('app/Apps/CmsproPolling/Routes/user.php'));

        // 用户端 API 路由
        Route::prefix('api/user/cmspro/polling')
            ->namespace('App\Apps\CmsproPolling\Controllers\User')
            ->middleware(['web', 'auth:web'])
            ->group(base_path('app/Apps/CmsproPolling/Routes/user_api.php'));

        // 前台页面路由（公开访问）
        Route::prefix('cmspro/polling')
            ->namespace('App\Apps\CmsproPolling\Controllers\Home')
            ->middleware(['web'])
            ->group(base_path('app/Apps/CmsproPolling/Routes/home.php'));

        // 前台 API 路由（公开访问，含游客投票）
        Route::prefix('cmspro/polling')
            ->namespace('App\Apps\CmsproPolling\Controllers\Home')
            ->middleware(['web', 'throttle:60,1'])
            ->group(base_path('app/Apps/CmsproPolling/Routes/home_api.php'));
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path('app/Apps/CmsproPolling/Views'),
            'cmspro.polling'
        );
    }

    protected function registerHooks(): void
    {
        $hooksClass = Hooks::class;
        if (!class_exists($hooksClass)) {
            return;
        }

        (new $hooksClass)->register(app(\App\Services\HookManager::class));
    }
}
