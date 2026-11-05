<?php

namespace App\Apps\CmsproPolling\Tests\Feature;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Models\PollingOption;
use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Apps\CmsproPolling\Services\ActivityService;
use App\Apps\CmsproPolling\Tests\PollingTestCase;

/**
 * 活动管理服务测试。
 *
 * 覆盖场景：
 * 1. 创建活动（含选项）成功与各类校验拦截
 * 2. 更新活动与选项整体替换策略
 * 3. 已产生投票记录的活动禁止更换投票类型
 * 4. 用户端越权拦截（limitUserId 限定本人活动）
 * 5. 状态流转（草稿 → 进行中 → 已结束）
 * 6. 删除活动级联清理选项与投票记录
 * 7. 列表查询：后台关键词过滤、用户端仅本人活动
 * 8. 格式化输出：运行时截止自动结束判定
 */
class ActivityServiceTest extends PollingTestCase
{
    protected ActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ActivityService::class);
    }

    /**
     * 构造一份完整合法的活动数据（可通过 $overrides 覆盖任意字段）
     */
    protected function activityData(array $overrides = []): array
    {
        return array_merge([
            'title' => '功能调研投票',
            'description' => '选出你最需要的功能',
            'type' => PollingActivity::TYPE_SINGLE,
            'max_choices' => 1,
            'start_time' => now()->subDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'status' => PollingActivity::STATUS_ACTIVE,
            'require_login' => 0,
            'votes_per_user' => 1,
            'ip_limit' => 0,
            'anonymous' => 0,
            'result_visibility' => PollingActivity::VISIBILITY_ALWAYS,
            'sort' => 0,
            'options' => [
                ['title' => '选项一', 'description' => '', 'image' => '', 'sort' => 1],
                ['title' => '选项二', 'description' => '', 'image' => '', 'sort' => 2],
                ['title' => '选项三', 'description' => '', 'image' => '', 'sort' => 3],
            ],
        ], $overrides);
    }

    public function test_创建活动含选项成功(): void
    {
        $result = $this->service->store($this->activityData(), 0);

        $this->assertSame(0, $result['code']);
        $this->assertSame('创建成功', $result['message']);

        $activityId = $result['data']['id'];
        $this->assertDatabaseHas('app_cmspro_polling_activities', [
            'id' => $activityId,
            'title' => '功能调研投票',
            'user_id' => 0,
            'status' => PollingActivity::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseCount('app_cmspro_polling_options', 3);
        $this->assertCount(3, $result['data']['options']);

        // 格式化输出包含类型与状态文案
        $this->assertSame('单选投票', $result['data']['type_text']);
        $this->assertSame('进行中', $result['data']['status_text']);
        $this->assertTrue($result['data']['is_votable']);
    }

    public function test_创建活动标题为空被拦截(): void
    {
        $result = $this->service->store($this->activityData(['title' => '  ']), 0);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('活动标题不能为空', $result['message']);
        $this->assertDatabaseCount('app_cmspro_polling_activities', 0);
    }

    public function test_创建活动无效类型被拦截(): void
    {
        $result = $this->service->store($this->activityData(['type' => 'invalid']), 0);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('无效的投票类型', $result['message']);
    }

    public function test_创建活动有效选项少于两个被拦截(): void
    {
        $options = [
            ['title' => '唯一选项', 'description' => '', 'image' => '', 'sort' => 1],
        ];
        $result = $this->service->store($this->activityData(['options' => $options]), 0);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('至少需要2个有效选项', $result['message']);
    }

    public function test_创建多选活动最多可选数至少为2(): void
    {
        $result = $this->service->store($this->activityData([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 1,
        ]), 0);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('多选投票最多可选数不能少于2', $result['message']);
    }

    public function test_创建多选活动选项数不能少于最多可选数(): void
    {
        $result = $this->service->store($this->activityData([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 5,
        ]), 0);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('选项数量不能少于多选最多可选数', $result['message']);
    }

    public function test_创建活动结束时间不晚于开始时间被拦截(): void
    {
        $time = now()->format('Y-m-d H:i:s');

        $result = $this->service->store($this->activityData([
            'start_time' => $time,
            'end_time' => $time,
        ]), 0);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('结束时间必须晚于开始时间', $result['message']);
    }

    public function test_更新活动整体替换选项(): void
    {
        $created = $this->service->store($this->activityData(), 0);
        $activityId = $created['data']['id'];
        $oldOptionId = $created['data']['options'][0]['id'];

        $result = $this->service->update($activityId, $this->activityData([
            'title' => '更新后的标题',
            'options' => [
                ['title' => '全新选项甲', 'description' => '', 'image' => '', 'sort' => 1],
                ['title' => '全新选项乙', 'description' => '', 'image' => '', 'sort' => 2],
            ],
        ]));

        $this->assertSame(0, $result['code']);
        $this->assertSame('更新后的标题', $result['data']['title']);

        // 旧选项被整体替换清理
        $this->assertDatabaseMissing('app_cmspro_polling_options', ['id' => $oldOptionId]);
        $this->assertSame(2, PollingOption::where('activity_id', $activityId)->count());
        $this->assertDatabaseHas('app_cmspro_polling_options', ['activity_id' => $activityId, 'title' => '全新选项甲']);
    }

    public function test_已有投票记录的活动禁止更换投票类型(): void
    {
        $created = $this->service->store($this->activityData(), 0);
        $activityId = $created['data']['id'];
        $optionId = $created['data']['options'][0]['id'];

        // 模拟已产生一条投票记录
        PollingRecord::create([
            'activity_id' => $activityId,
            'option_id' => $optionId,
            'user_id' => 0,
            'vote_no' => 1,
            'rank' => 0,
            'ip' => '1.1.1.1',
        ]);

        $result = $this->service->update($activityId, $this->activityData([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 2,
        ]));

        $this->assertSame(40001, $result['code']);
        $this->assertSame('该活动已有投票记录，不允许更换投票类型', $result['message']);
    }

    public function test_未产生记录的活动允许更换投票类型(): void
    {
        $created = $this->service->store($this->activityData(), 0);
        $activityId = $created['data']['id'];

        $result = $this->service->update($activityId, $this->activityData([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 2,
        ]));

        $this->assertSame(0, $result['code']);
        $this->assertSame(PollingActivity::TYPE_MULTIPLE, $result['data']['type']);
    }

    public function test_用户端越权更新他人活动被拦截(): void
    {
        $owner = $this->createUser('owner');
        $created = $this->service->store($this->activityData(), $owner->id);
        $activityId = $created['data']['id'];

        $intruderId = $this->createUser('intruder')->id;

        $result = $this->service->update($activityId, $this->activityData(), $intruderId);

        $this->assertSame(40301, $result['code']);
        $this->assertSame('无权操作他人创建的投票', $result['message']);
        // 原数据未被改动
        $this->assertDatabaseHas('app_cmspro_polling_activities', [
            'id' => $activityId,
            'title' => '功能调研投票',
        ]);
    }

    public function test_用户端越权删除他人活动被拦截(): void
    {
        $owner = $this->createUser('owner');
        $created = $this->service->store($this->activityData(), $owner->id);
        $activityId = $created['data']['id'];

        $intruderId = $this->createUser('intruder')->id;

        $result = $this->service->destroy($activityId, $intruderId);

        $this->assertSame(40301, $result['code']);
        $this->assertDatabaseHas('app_cmspro_polling_activities', ['id' => $activityId]);
    }

    public function test_用户端本人操作自己的活动放行(): void
    {
        $owner = $this->createUser('owner');
        $created = $this->service->store($this->activityData(), $owner->id);
        $activityId = $created['data']['id'];

        $updated = $this->service->update($activityId, $this->activityData(['title' => '本人更新']), $owner->id);
        $this->assertSame(0, $updated['code']);

        $deleted = $this->service->destroy($activityId, $owner->id);
        $this->assertSame(0, $deleted['code']);
    }

    public function test_活动状态流转_草稿到进行中到已结束(): void
    {
        $created = $this->service->store($this->activityData(['status' => PollingActivity::STATUS_DRAFT]), 0);
        $activityId = $created['data']['id'];

        $publish = $this->service->updateStatus($activityId, PollingActivity::STATUS_ACTIVE);
        $this->assertSame(0, $publish['code']);
        $this->assertSame('进行中', $publish['data']['status_text']);
        $this->assertDatabaseHas('app_cmspro_polling_activities', ['id' => $activityId, 'status' => 1]);

        $end = $this->service->updateStatus($activityId, PollingActivity::STATUS_ENDED);
        $this->assertSame(0, $end['code']);
        $this->assertDatabaseHas('app_cmspro_polling_activities', ['id' => $activityId, 'status' => 2]);
    }

    public function test_更新为无效状态被拦截(): void
    {
        $created = $this->service->store($this->activityData(), 0);
        $activityId = $created['data']['id'];

        $result = $this->service->updateStatus($activityId, 99);

        $this->assertSame(40001, $result['code']);
        $this->assertSame('无效的活动状态', $result['message']);
    }

    public function test_删除活动级联清理选项与投票记录(): void
    {
        $created = $this->service->store($this->activityData(), 0);
        $activityId = $created['data']['id'];
        $optionId = $created['data']['options'][0]['id'];

        PollingRecord::create([
            'activity_id' => $activityId,
            'option_id' => $optionId,
            'user_id' => 0,
            'vote_no' => 1,
            'rank' => 0,
            'ip' => '1.1.1.1',
        ]);

        $result = $this->service->destroy($activityId);

        $this->assertSame(0, $result['code']);
        $this->assertDatabaseMissing('app_cmspro_polling_activities', ['id' => $activityId]);
        $this->assertDatabaseMissing('app_cmspro_polling_options', ['activity_id' => $activityId]);
        $this->assertDatabaseMissing('app_cmspro_polling_records', ['activity_id' => $activityId]);
    }

    public function test_删除不存在的活动返回40401(): void
    {
        $result = $this->service->destroy(99999);

        $this->assertSame(40401, $result['code']);
        $this->assertSame('投票活动不存在', $result['message']);
    }

    public function test_后台列表按关键词与状态过滤(): void
    {
        $this->service->store($this->activityData(['title' => '年度评选投票']), 0);
        $this->service->store($this->activityData([
            'title' => '社区话题投票',
            'status' => PollingActivity::STATUS_DRAFT,
        ]), 0);

        // 关键词过滤
        $keywordResult = $this->service->getList(['keyword' => '年度评选']);
        $this->assertSame(0, $keywordResult['code']);
        $this->assertCount(1, $keywordResult['data']['items']);
        $this->assertSame('年度评选投票', $keywordResult['data']['items'][0]['title']);

        // 状态过滤
        $draftResult = $this->service->getList(['status' => PollingActivity::STATUS_DRAFT]);
        $this->assertCount(1, $draftResult['data']['items']);
        $this->assertSame('社区话题投票', $draftResult['data']['items'][0]['title']);
    }

    public function test_用户端列表仅返回本人创建的活动(): void
    {
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $this->service->store($this->activityData(['title' => 'Alice的活动']), $alice->id);
        $this->service->store($this->activityData(['title' => 'Bob的活动']), $bob->id);

        $result = $this->service->getUserOwnList($alice->id, []);

        $this->assertSame(0, $result['code']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertSame('Alice的活动', $result['data']['items'][0]['title']);
    }

    public function test_格式化输出_到期活动运行时判定为已结束(): void
    {
        $created = $this->service->store($this->activityData([
            'end_time' => now()->subMinute()->format('Y-m-d H:i:s'),
        ]), 0);

        $activity = PollingActivity::find($created['data']['id']);
        $formatted = $this->service->formatActivity($activity);

        // 数据库 status 仍为进行中，运行时判定为已结束
        $this->assertSame(PollingActivity::STATUS_ACTIVE, $formatted['status']);
        $this->assertSame(PollingActivity::STATUS_ENDED, $formatted['effective_status']);
        $this->assertSame('已结束', $formatted['effective_status_text']);
        $this->assertFalse($formatted['is_votable']);
    }
}
