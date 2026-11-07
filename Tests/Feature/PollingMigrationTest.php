<?php

namespace App\Apps\CmsproPolling\Tests\Feature;

use App\Apps\CmsproPolling\Tests\PollingTestCase;
use Illuminate\Support\Facades\Schema;

/**
 * 应用自带迁移的结构断言。
 *
 * PollingTestCase::afterRefreshingDatabase 会补跑应用迁移，
 * 这里验证迁移产物：activities 表的列表筛选/排序索引齐备。
 */
class PollingMigrationTest extends PollingTestCase
{
    public function test_活动表筛选与排序字段建有索引(): void
    {
        $table = 'app_cmspro_polling_activities';

        $this->assertTrue(
            Schema::hasIndex($table, $table . '_type_index'),
            'activities 表缺少 type 索引（列表类型筛选字段）'
        );
        $this->assertTrue(
            Schema::hasIndex($table, $table . '_create_time_index'),
            'activities 表缺少 create_time 索引（列表默认排序字段）'
        );
    }
}
