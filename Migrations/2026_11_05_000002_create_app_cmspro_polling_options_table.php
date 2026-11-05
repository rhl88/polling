<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_polling_options')) {
            return;
        }

        Schema::create('app_cmspro_polling_options', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('activity_id')->comment('所属活动ID，关联app_cmspro_polling_activities表');
            $table->string('title', 200)->comment('选项标题');
            $table->string('description', 500)->nullable()->comment('选项描述说明');
            $table->string('image', 255)->nullable()->comment('选项图片路径（相对路径）');
            $table->unsignedInteger('sort')->default(0)->comment('排序，越小越靠前');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            // 索引使用 Laravel 自动生成名（含表名前缀），避免 SQLite 全局索引名冲突
            $table->index('activity_id');
        });

        // 表注释为 MySQL 专有语法，SQLite（测试内存库）不支持，需按驱动判断
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_polling_options` COMMENT '投票选项表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_polling_options');
    }
};
