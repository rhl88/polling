<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_polling_activities')) {
            return;
        }

        Schema::create('app_cmspro_polling_activities', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->string('title', 200)->comment('活动标题');
            $table->text('description')->nullable()->comment('活动描述');
            $table->string('type', 20)->default('single')->comment('投票类型：single-单选 multiple-多选 ranking-排序');
            $table->unsignedTinyInteger('max_choices')->default(1)->comment('多选投票时每人最多可选选项数');
            $table->timestamp('start_time')->nullable()->comment('投票开始时间');
            $table->timestamp('end_time')->nullable()->comment('投票结束时间');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态：0-草稿 1-进行中 2-已结束');
            $table->unsignedTinyInteger('require_login')->default(0)->comment('是否需要登录：0-允许游客投票 1-需要登录');
            $table->unsignedInteger('votes_per_user')->default(1)->comment('每人限投次数');
            $table->unsignedInteger('ip_limit')->default(0)->comment('同一IP最多可提交投票次数，0-不限制');
            $table->unsignedTinyInteger('anonymous')->default(0)->comment('匿名投票：0-公开投票人 1-匿名');
            $table->string('result_visibility', 20)->default('always')->comment('结果可见性：always-实时可见 after_vote-投票后可见 end-结束后可见 never-不公开');
            $table->unsignedBigInteger('user_id')->default(0)->comment('创建者用户ID，0-后台管理员创建');
            $table->unsignedInteger('sort')->default(0)->comment('排序，越小越靠前');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            // 索引使用 Laravel 自动生成名（含表名前缀）：
            // SQLite 的索引名为全局命名空间，与 users.idx_user_id 等系统索引短名会冲突
            $table->index('status');
            $table->index('user_id');
            $table->index('start_time');
            $table->index('end_time');
        });

        // 表注释为 MySQL 专有语法，SQLite（测试内存库）不支持，需按驱动判断
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_polling_activities` COMMENT '投票活动表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_polling_activities');
    }
};
