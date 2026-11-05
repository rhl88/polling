<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_polling_records')) {
            return;
        }

        Schema::create('app_cmspro_polling_records', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('activity_id')->comment('所属活动ID，关联app_cmspro_polling_activities表');
            $table->unsignedBigInteger('option_id')->comment('选项ID，关联app_cmspro_polling_options表');
            $table->unsignedBigInteger('user_id')->default(0)->comment('投票用户ID，0-游客');
            $table->unsignedInteger('vote_no')->default(1)->comment('投票批次号，同一次提交的多条记录批次相同，从1递增');
            $table->unsignedTinyInteger('rank')->default(0)->comment('排序投票名次（ranking类型使用），0-非排序投票');
            $table->string('ip', 45)->nullable()->comment('投票IP');
            $table->timestamp('create_time')->nullable()->comment('投票时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            // 索引使用 Laravel 自动生成名（含表名前缀），避免 SQLite 全局索引名冲突
            $table->index('activity_id');
            $table->index(['activity_id', 'user_id', 'vote_no']);
            $table->index(['activity_id', 'ip']);
            $table->index('option_id');
            $table->index('user_id');
        });

        // 表注释为 MySQL 专有语法，SQLite（测试内存库）不支持，需按驱动判断
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_polling_records` COMMENT '投票记录表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_polling_records');
    }
};
