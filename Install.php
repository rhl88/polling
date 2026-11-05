<?php

namespace App\Apps\CmsproPolling;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Install
{
    /**
     * 应用标识
     */
    protected string $appId = 'cmspro.polling';

    public function install(): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->seedDemoActivity();
    }

    public function uninstall(): void
    {
        $this->rollbackMigrations();
        Cache::flush();
    }

    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
    }

    /**
     * 从 manifest.json 同步配置到数据库
     * - 新增配置：写入默认值
     * - 已有配置：仅更新结构（name/type/tips/sort），保留用户设置的 value
     * - 已删除配置：清理 DB 残留
     */
    protected function seedDefaultConfigs(): void
    {
        if (!class_exists(\App\Models\ConfigGroup::class) || !class_exists(\App\Models\ConfigItem::class)) {
            return;
        }

        $manifestPath = __DIR__ . '/manifest.json';
        if (!file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $configGroups = $manifest['config_groups'] ?? [];

        $manifestCodes = [];
        $prefix = $this->configCodePrefix();

        foreach ($configGroups as $groupDef) {
            $group = \App\Models\ConfigGroup::updateOrCreate(
                ['code' => $prefix . $groupDef['name']],
                [
                    'name' => $groupDef['title'],
                    'app_id' => $this->appId,
                    'sort' => 0,
                    'status' => 1,
                ]
            );

            foreach ($groupDef['items'] as $index => $item) {
                $fullCode = $prefix . $item['name'];
                $manifestCodes[] = $fullCode;

                $existing = \App\Models\ConfigItem::where('code', $fullCode)->first();

                if ($existing) {
                    // 已有记录 → 仅更新结构信息，不覆盖用户设置的 value
                    $existing->update([
                        'group_id' => $group->id,
                        'name' => $item['title'],
                        'type' => $item['type'] ?? 'text',
                        'tips' => $item['tips'] ?? '',
                        'sort' => $index,
                        'status' => 1,
                    ]);
                } else {
                    // 新增配置 → 写入默认值
                    \App\Models\ConfigItem::create([
                        'code' => $fullCode,
                        'group_id' => $group->id,
                        'name' => $item['title'],
                        'type' => $item['type'] ?? 'text',
                        'value' => $item['value'] ?? '',
                        'tips' => $item['tips'] ?? '',
                        'sort' => $index,
                        'status' => 1,
                    ]);
                }
            }
        }

        // 清理 manifest 中已删除的配置项（防残留）。
        // like 模式中的 _ 为单字符通配符，可同时匹配历史版本错误前缀
        // （app_cmspro.polling_xxx，点号未转下划线）写入的残留，实现自动纠偏。
        \App\Models\ConfigItem::where('code', 'like', $prefix . '%')
            ->whereNotIn('code', $manifestCodes)
            ->delete();

        // 清理 manifest 中已删除的配置组（无子项的组）
        \App\Models\ConfigGroup::where('app_id', $this->appId)
            ->whereDoesntHave('items')
            ->delete();
    }

    /**
     * 配置项 code 前缀
     *
     * 与框架 AppInstallerService 保持一致：点号转下划线，
     * 即 cmspro.polling → app_cmspro_polling_
     */
    protected function configCodePrefix(): string
    {
        return 'app_' . str_replace('.', '_', $this->appId) . '_';
    }

    /**
     * 种子示例投票活动（便于安装后立即体验）
     */
    protected function seedDemoActivity(): void
    {
        if (DB::table('app_cmspro_polling_activities')->where('title', '你最期待的投票功能是什么？')->exists()) {
            return;
        }

        $now = now();
        $endTime = $now->copy()->addDays(30);

        $activityId = DB::table('app_cmspro_polling_activities')->insertGetId([
            'title' => '你最期待的投票功能是什么？',
            'description' => '感谢安装投票系统！这是一个示例投票活动，您可以随时删除。请在下方选出您最期待的功能，帮助我们改进产品。',
            'type' => 'single',
            'max_choices' => 1,
            'start_time' => $now,
            'end_time' => $endTime,
            'status' => 1,
            'require_login' => 0,
            'votes_per_user' => 1,
            'ip_limit' => 0,
            'anonymous' => 0,
            'result_visibility' => 'always',
            'user_id' => 0,
            'sort' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);

        $options = [
            ['图文投票', '支持选项配图，评选类投票更直观'],
            ['实时大屏', '活动现场大屏展示实时投票结果'],
            ['防刷票增强', '验证码、设备指纹等更强的防刷手段'],
            ['多轮投票', '一个活动内分多轮晋级投票'],
        ];

        foreach ($options as $index => $option) {
            DB::table('app_cmspro_polling_options')->insert([
                'activity_id' => $activityId,
                'title' => $option[0],
                'description' => $option[1],
                'image' => '',
                'sort' => $index + 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }
    }

    /**
     * 迁移兜底：Windows 等环境下系统迁移可能未执行，此处直接 require 迁移文件执行 up()
     */
    protected function runMigrations(): void
    {
        $migrationsPath = __DIR__ . '/Migrations';
        $migrationFiles = glob($migrationsPath . '/*.php');

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (DB::table('migrations')->where('migration', $migrationName)->exists()) {
                continue;
            }

            $migration = require $file;
            if (method_exists($migration, 'up')) {
                try {
                    $migration->up();

                    DB::table('migrations')->insert([
                        'migration' => $migrationName,
                        'batch' => DB::table('migrations')->max('batch') + 1,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('应用迁移执行失败', [
                        'app' => $this->appId,
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * 迁移回滚兜底：直接 require 迁移文件执行 down()，确保卸载后表被清理
     */
    protected function rollbackMigrations(): void
    {
        $migrationsPath = __DIR__ . '/Migrations';
        $migrationFiles = glob($migrationsPath . '/*.php');

        foreach (array_reverse($migrationFiles) as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (!DB::table('migrations')->where('migration', $migrationName)->exists()) {
                continue;
            }

            $migration = require $file;
            if (method_exists($migration, 'down')) {
                try {
                    $migration->down();

                    DB::table('migrations')->where('migration', $migrationName)->delete();
                } catch (\Throwable $e) {
                    Log::error('应用迁移回滚失败', [
                        'app' => $this->appId,
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
