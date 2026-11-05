<?php

namespace App\Apps\CmsproPolling\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 应用后台权限校验中间件（开发文档第十五章 / F50）
 *
 * 框架 CheckPermission 仅对 api/admin/ 前缀路由推导权限码，应用后台路由
 * （admin/cmspro/polling/*）不在其覆盖范围，故按同一机制自行实现：
 * - 超管放行；
 * - 投票记录（页面与接口）要求 cmspro.polling.record.view；
 * - 统计导出接口要求 cmspro.polling.stats.export；
 * - 活动写操作（增/改/删/状态）要求 cmspro.polling.activity.manage；
 * - 其余后台访问要求 cmspro.polling.manage；
 * - 权限码未注册到 admin_permissions 表时放行（与框架机制一致）。
 */
class CheckManagePermission
{
    /** 投票管理（基础访问） */
    private const CODE_MANAGE = 'cmspro.polling.manage';

    /** 活动管理（写操作） */
    private const CODE_ACTIVITY_MANAGE = 'cmspro.polling.activity.manage';

    /** 查看投票记录 */
    private const CODE_RECORD_VIEW = 'cmspro.polling.record.view';

    /** 导出统计报告 */
    private const CODE_STATS_EXPORT = 'cmspro.polling.stats.export';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('admin');

        if (! $user) {
            return response()->json(ApiResponse::error(40003, '登录已过期，请重新登录'), 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $code = $this->requiredCode($request);

        // 权限码不存在于 admin_permissions 表则放行（与框架 CheckPermission 机制一致）
        if (! $this->permissionExists($code)) {
            return $next($request);
        }

        $permissions = cache()->remember(
            "user:{$user->id}:permissions",
            config('cmspro.cache.permission_ttl', 3600),
            fn () => $user->getAllPermissions()
        );

        if (! in_array($code, $permissions)) {
            return $this->deny($request);
        }

        return $next($request);
    }

    /**
     * 根据路由 URI 与 HTTP 方法推导所需权限码
     */
    private function requiredCode(Request $request): string
    {
        $route = $request->route();
        $uri = $route ? $route->uri() : $request->path();
        $method = strtoupper($request->method());

        // 投票记录（后台页面与接口）
        if (str_contains($uri, '/records')) {
            return self::CODE_RECORD_VIEW;
        }

        // 统计报告导出（判断先于活动写操作，避免被 /activities 前缀吞掉）
        if (str_contains($uri, '/export/')) {
            return self::CODE_STATS_EXPORT;
        }

        // 活动写操作（创建/更新/删除/状态变更）
        if (str_contains($uri, '/activities')
            && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return self::CODE_ACTIVITY_MANAGE;
        }

        // 其余后台访问（列表/详情/表单页/统计页等）
        return self::CODE_MANAGE;
    }

    /**
     * 权限不足响应：AJAX 返回 JSON 403，普通页面请求返回 403 页面
     */
    private function deny(Request $request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(ApiResponse::error(40101, '无访问权限'), 403);
        }

        abort(403, '无访问权限');
    }

    /**
     * 检查权限码是否存在于 admin_permissions 表
     */
    private function permissionExists(string $code): bool
    {
        return cache()->remember(
            "permission:exists:{$code}",
            config('cmspro.cache.permission_ttl', 3600),
            fn () => DB::table('admin_permissions')->where('code', $code)->exists()
        );
    }
}
