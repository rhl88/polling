<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 活动表补列表筛选/排序索引：type（类型筛选）、create_time（默认排序）。
     *
     * 索引名沿用 Laravel 自动命名（表_列_index）：SQLite 的索引名为
     * 全局命名空间，自动名含表名前缀可规避与其他表索引短名冲突；
     * hasIndex 前置检查保证脚本幂等（重复执行不报错）。
     */
    public function up(): void
    {
        $table = 'app_cmspro_polling_activities';

        if (!Schema::hasTable($table)) {
            return;
        }

        if (!Schema::hasIndex($table, $table . '_type_index')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('type');
            });
        }

        if (!Schema::hasIndex($table, $table . '_create_time_index')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('create_time');
            });
        }
    }

    public function down(): void
    {
        $table = 'app_cmspro_polling_activities';

        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropIndex(['type']);
            $blueprint->dropIndex(['create_time']);
        });
    }
};
