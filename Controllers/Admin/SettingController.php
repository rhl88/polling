<?php

namespace App\Apps\CmsproPolling\Controllers\Admin;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * 投票设置控制器（polling_settings 配置组）。
 *
 * 配置数据存于 config_items 表，code 前缀 app_cmspro_polling_。
 * 相比直接放行任意 key 的写法，这里做了两层防护：
 * 1. 白名单校验——仅允许 manifest config_groups 注册的配置项写入，
 *    杜绝伪造 key 向 config_items 插入脏数据；
 * 2. 类型校验——number 配置必须为非负整数，switch 配置仅允许 0/1。
 * 两层校验的依据均动态读取 manifest，后续新增配置无需改本控制器。
 */
class SettingController extends Controller
{
    /** 配置项 code 前缀（与 Install::configCodePrefix 一致） */
    protected string $codePrefix = 'app_cmspro_polling_';

    /**
     * 读取投票设置（去前缀后以 key => value 返回）
     */
    public function index()
    {
        $settings = DB::table('config_items')
            ->where('code', 'like', $this->codePrefix . '%')
            ->get()
            ->mapWithKeys(function ($item) {
                $key = str_replace($this->codePrefix, '', $item->code);

                return [$key => $item->value];
            })
            ->toArray();

        return response()->json(ApiResponse::success($settings));
    }

    /**
     * 更新投票设置（白名单内配置项）
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        $settings = $data['settings'];
        $manifestItems = $this->manifestSettingItems();

        // 白名单校验：仅允许 manifest 注册的配置项
        $unknown = array_diff(array_keys($settings), array_keys($manifestItems));
        if ($unknown !== []) {
            return response()->json(
                ApiResponse::error(40001, '不支持的配置项：' . implode(', ', $unknown)),
                422
            );
        }

        // 类型校验：number → 非负整数；switch → 0/1
        foreach ($settings as $key => $value) {
            $type = $manifestItems[$key]['type'] ?? 'text';
            $title = $manifestItems[$key]['title'] ?? $key;

            if ($type === 'number' && ! ctype_digit((string) $value)) {
                return response()->json(
                    ApiResponse::error(40001, "配置项「{$title}」必须为非负整数"),
                    422
                );
            }

            if ($type === 'switch' && ! in_array((string) $value, ['0', '1'], true)) {
                return response()->json(
                    ApiResponse::error(40001, "配置项「{$title}」仅允许开启或关闭"),
                    422
                );
            }
        }

        foreach ($settings as $key => $value) {
            DB::table('config_items')->updateOrInsert(
                ['code' => $this->codePrefix . $key],
                ['value' => (string) $value, 'update_time' => now()]
            );
        }

        return response()->json(ApiResponse::success(null, '设置更新成功'));
    }

    /**
     * 从 manifest 读取配置组定义，以 name => item 映射返回
     */
    protected function manifestSettingItems(): array
    {
        $manifestPath = dirname(__DIR__, 2) . '/manifest.json';
        if (! file_exists($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            return [];
        }

        $items = [];
        foreach ($manifest['config_groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $items[$item['name']] = $item;
            }
        }

        return $items;
    }
}
