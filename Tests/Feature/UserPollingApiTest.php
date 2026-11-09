<?php

namespace App\Apps\CmsproPolling\Tests\Feature;

use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Apps\CmsproPolling\Tests\PollingTestCase;
use App\Models\ConfigItem;

/**
 * 用户中心端点集成测试。
 *
 * 走完整 HTTP 链路（路由 → auth:web → 控制器 → 服务），覆盖：
 * 1. 普通（非 JSON）表单提交的校验失败回归——ValidationException 由框架
 *    渲染器 redirect back（常见问题第一章），回归保障浏览器原生表单
 *    链路不因校验异常抛 500（此前用例全部走 postJson，存在链路盲区）；
 * 2. 后台「投票设置」关闭用户端发起投票后，用户中心创建接口被拦截。
 */
class UserPollingApiTest extends PollingTestCase
{
    /**
     * 构造一份合法的活动创建数据（可通过 $overrides 覆盖任意字段）
     */
    protected function activityPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '用户中心发起的投票',
            'description' => '',
            'type' => 'single',
            'max_choices' => 1,
            'start_time' => '',
            'end_time' => '',
            'status' => 1,
            'require_login' => 0,
            'votes_per_user' => 1,
            'ip_limit' => 0,
            'anonymous' => 0,
            'result_visibility' => 'always',
            'sort' => 0,
            'options' => [
                ['title' => '选项一', 'description' => '', 'sort' => 1],
                ['title' => '选项二', 'description' => '', 'sort' => 2],
            ],
        ], $overrides);
    }

    /**
     * 写入后台「投票设置」配置项（模拟管理员在配置页保存）
     */
    protected function seedPollingConfig(string $name, string $value): void
    {
        ConfigItem::create([
            'group_id' => 0,
            'name' => $name,
            'code' => 'app_cmspro_polling_' . $name,
            'value' => $value,
            'type' => 'switch',
            'tips' => '',
            'sort' => 0,
            'status' => 1,
        ]);
    }

    public function test_普通表单提交校验失败重定向返回而非500(): void
    {
        $user = $this->createUser('formposter');

        // 模拟浏览器原生表单提交：无 Accept: application/json、无 X-Requested-With，
        // 且缺少全部必填字段 → ValidationException 由框架渲染 redirect()->back()
        $response = $this->actingAs($user, 'web')
            ->post('/api/user/cmspro/polling/activities', []);

        $response->assertStatus(302);
    }

    public function test_普通表单提交非法参数被重定向返回(): void
    {
        $user = $this->createUser('formposter');

        // 携带非法投票类型（type=invalid 触发校验失败），仍走普通表单链路
        $response = $this->actingAs($user, 'web')
            ->post('/api/user/cmspro/polling/activities', $this->activityPayload([
                'type' => 'invalid',
            ]));

        $response->assertStatus(302);
    }

    public function test_关闭用户端发起投票后创建接口被拦截(): void
    {
        $this->seedPollingConfig('allow_user_create', '0');
        $user = $this->createUser('blocked');

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/user/cmspro/polling/activities', $this->activityPayload());

        $response->assertOk()
            ->assertJsonPath('code', 40301)
            ->assertJsonPath('message', '管理员已关闭用户端发起投票功能');
    }

    public function test_开启用户端发起投票后创建接口放行(): void
    {
        $this->seedPollingConfig('allow_user_create', '1');
        $user = $this->createUser('allowed');

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/user/cmspro/polling/activities', $this->activityPayload());

        $response->assertOk()
            ->assertJsonPath('code', 0);

        $this->assertDatabaseHas('app_cmspro_polling_activities', [
            'title' => '用户中心发起的投票',
            'user_id' => $user->id,
        ]);
    }

    public function test_参与记录投票时间为标准日期时间格式(): void
    {
        $user = $this->createUser('recordviewer');
        $activity = $this->makeActivity();
        $optionIds = $this->makeOptions($activity);

        // 固定投票时间；create_time 不在 fillable 白名单，走实例属性赋值
        // 以防被 CREATED_AT 自动填充覆盖
        $record = new PollingRecord();
        $record->activity_id = $activity->id;
        $record->option_id = $optionIds[0];
        $record->user_id = $user->id;
        $record->vote_no = 1;
        $record->rank = 0;
        $record->ip = '1.1.1.1';
        $record->create_time = '2026-11-14 10:29:32';
        $record->update_time = '2026-11-14 10:29:32';
        $record->save();

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/user/cmspro/polling/records');

        $response->assertOk();

        // datetime cast 属性直接访问得到 Carbon 对象（进 JSON 会变 ISO8601），
        // 参与记录接口须输出标准 Y-m-d H:i:s 格式字符串
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame('2026-11-14 10:29:32', $items[0]['create_time']);
    }
}
