<?php

namespace App\Apps\CmsproPolling\Tests\Feature;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Apps\CmsproPolling\Services\VoteService;
use App\Apps\CmsproPolling\Tests\PollingTestCase;
use App\Services\HookManager;

/**
 * 投票核心服务测试。
 *
 * 覆盖场景：
 * 1. 三种投票类型（单选/多选/排序）的正常提交与类型规则拦截
 * 2. 活动状态与时间窗口校验（草稿/已结束/未开始/运行时截止判定）
 * 3. 登录校验（需登录活动拦截游客）
 * 4. 防刷票：限投次数（用户维度/IP维度）、同IP批次上限
 * 5. 批次号生成与递增
 * 6. 统计：参与人次去重、多选占比、排序投票 Borda 计分与名次分布
 * 7. 结果可见性四种模式的判定
 * 8. 自定义钩子触发（polling.vote.submitted / polling.activity.stats）
 * 9. CSV 导出：BOM 头、匿名脱敏
 */
class VoteServiceTest extends PollingTestCase
{
    protected VoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VoteService::class);
    }

    public function test_游客单选投票成功(): void
    {
        $activity = $this->makeActivity();
        [$optionA, $optionB] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(0, $result['code']);
        $this->assertSame(1, $result['data']['vote_no']);
        $this->assertSame(1, $result['data']['option_count']);

        $this->assertDatabaseHas('app_cmspro_polling_records', [
            'activity_id' => $activity->id,
            'option_id' => $optionA,
            'user_id' => 0,
            'vote_no' => 1,
            'rank' => 0,
            'ip' => '1.1.1.1',
        ]);
        $this->assertDatabaseMissing('app_cmspro_polling_records', [
            'option_id' => $optionB,
        ]);
    }

    public function test_单选投票选择多个选项被拦截(): void
    {
        $activity = $this->makeActivity();
        [$optionA, $optionB] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA, $optionB], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertStringContainsString('只能选择1个选项', $result['message']);
        $this->assertDatabaseCount('app_cmspro_polling_records', 0);
    }

    public function test_多选投票在上限内提交成功(): void
    {
        $activity = $this->makeActivity([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 2,
        ]);
        [$optionA, $optionB] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA, $optionB], [], null, '1.1.1.1');

        $this->assertSame(0, $result['code']);
        $this->assertSame(2, $result['data']['option_count']);
        $this->assertDatabaseCount('app_cmspro_polling_records', 2);
    }

    public function test_多选投票超出可选上限被拦截(): void
    {
        $activity = $this->makeActivity([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 2,
        ]);
        [$optionA, $optionB, $optionC] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA, $optionB, $optionC], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertStringContainsString('1到2个选项', $result['message']);
        $this->assertDatabaseCount('app_cmspro_polling_records', 0);
    }

    public function test_排序投票按提交顺序记录名次(): void
    {
        $activity = $this->makeActivity(['type' => PollingActivity::TYPE_RANKING]);
        [$optionA, $optionB, $optionC] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA, $optionB, $optionC], [], null, '1.1.1.1');

        $this->assertSame(0, $result['code']);
        $this->assertDatabaseHas('app_cmspro_polling_records', ['option_id' => $optionA, 'rank' => 1]);
        $this->assertDatabaseHas('app_cmspro_polling_records', ['option_id' => $optionB, 'rank' => 2]);
        $this->assertDatabaseHas('app_cmspro_polling_records', ['option_id' => $optionC, 'rank' => 3]);
    }

    public function test_排序投票支持显式名次映射(): void
    {
        $activity = $this->makeActivity(['type' => PollingActivity::TYPE_RANKING]);
        [$optionA, $optionB, $optionC] = $this->makeOptions($activity);

        $rankMap = [$optionC => 1, $optionA => 2, $optionB => 3];
        $result = $this->service->submitVote($activity, [$optionA, $optionB, $optionC], $rankMap, null, '1.1.1.1');

        $this->assertSame(0, $result['code']);
        $this->assertDatabaseHas('app_cmspro_polling_records', ['option_id' => $optionC, 'rank' => 1]);
        $this->assertDatabaseHas('app_cmspro_polling_records', ['option_id' => $optionA, 'rank' => 2]);
        $this->assertDatabaseHas('app_cmspro_polling_records', ['option_id' => $optionB, 'rank' => 3]);
    }

    public function test_排序投票少于两个选项被拦截(): void
    {
        $activity = $this->makeActivity(['type' => PollingActivity::TYPE_RANKING]);
        [$optionA] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertStringContainsString('至少需要对2个选项排序', $result['message']);
    }

    public function test_草稿状态活动不可投票(): void
    {
        $activity = $this->makeActivity(['status' => PollingActivity::STATUS_DRAFT]);
        [$optionA] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertSame('该投票活动未发布', $result['message']);
    }

    public function test_已结束状态活动不可投票(): void
    {
        $activity = $this->makeActivity(['status' => PollingActivity::STATUS_ENDED]);
        [$optionA] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertSame('该投票活动已结束', $result['message']);
    }

    public function test_未到开始时间不可投票(): void
    {
        $activity = $this->makeActivity(['start_time' => now()->addDay()]);
        [$optionA] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertDatabaseCount('app_cmspro_polling_records', 0);
    }

    public function test_活动到期后运行时判定为已结束(): void
    {
        // status 仍为“进行中”，但 end_time 已过，应在运行时被判定为已结束
        $activity = $this->makeActivity(['end_time' => now()->subMinute()]);
        [$optionA] = $this->makeOptions($activity);

        $this->assertSame(PollingActivity::STATUS_ENDED, $activity->effective_status);
        $this->assertFalse($activity->isVotable());

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertSame('该投票活动已结束', $result['message']);
        // 运行时判定不写库，status 字段保持原值
        $this->assertDatabaseHas('app_cmspro_polling_activities', [
            'id' => $activity->id,
            'status' => PollingActivity::STATUS_ACTIVE,
        ]);
    }

    public function test_需要登录的活动拦截游客投票(): void
    {
        $activity = $this->makeActivity(['require_login' => 1]);
        [$optionA] = $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(40101, $result['code']);
        $this->assertSame('该投票活动需要登录后参与', $result['message']);
        $this->assertDatabaseCount('app_cmspro_polling_records', 0);
    }

    public function test_需要登录的活动允许登录用户投票(): void
    {
        $activity = $this->makeActivity(['require_login' => 1]);
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser();

        $result = $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');

        $this->assertSame(0, $result['code']);
        $this->assertDatabaseHas('app_cmspro_polling_records', [
            'option_id' => $optionA,
            'user_id' => $user->id,
        ]);
    }

    public function test_包含无效选项被拦截(): void
    {
        $activity = $this->makeActivity();
        $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [99999], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertSame('包含无效的投票选项', $result['message']);
        $this->assertDatabaseCount('app_cmspro_polling_records', 0);
    }

    public function test_提交空选项被拦截(): void
    {
        $activity = $this->makeActivity();
        $this->makeOptions($activity);

        $result = $this->service->submitVote($activity, [], [], null, '1.1.1.1');

        $this->assertSame(40001, $result['code']);
        $this->assertSame('请选择投票选项', $result['message']);
    }

    public function test_登录用户限投次数用尽后再次投票被拦截(): void
    {
        $activity = $this->makeActivity(['votes_per_user' => 1]);
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser();

        $first = $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');
        $this->assertSame(0, $first['code']);

        $second = $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');
        $this->assertSame(40001, $second['code']);
        $this->assertSame('您已达到该活动的投票次数上限', $second['message']);
    }

    public function test_登录用户限投次数按用户维度统计不受IP影响(): void
    {
        // 同一登录用户换 IP 投票，限投次数仍按用户维度累计
        $activity = $this->makeActivity(['votes_per_user' => 1]);
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser();

        $first = $this->service->submitVote($activity, [$optionA], [], $user, '2.2.2.2');
        $this->assertSame(0, $first['code']);

        $second = $this->service->submitVote($activity, [$optionA], [], $user, '3.3.3.3');
        $this->assertSame(40001, $second['code']);
    }

    public function test_游客限投次数按IP维度统计(): void
    {
        $activity = $this->makeActivity(['votes_per_user' => 1]);
        [$optionA] = $this->makeOptions($activity);

        // 同一 IP 的游客：第一次成功，第二次被限投拦截
        $first = $this->service->submitVote($activity, [$optionA], [], null, '4.4.4.4');
        $this->assertSame(0, $first['code']);

        $second = $this->service->submitVote($activity, [$optionA], [], null, '4.4.4.4');
        $this->assertSame(40001, $second['code']);

        // 不同 IP 的游客拥有独立的限投额度
        $third = $this->service->submitVote($activity, [$optionA], [], null, '5.5.5.5');
        $this->assertSame(0, $third['code']);
    }

    public function test_同IP批次上限拦截所有来源的投票(): void
    {
        // ip_limit=1：该 IP 下的全部批次（含登录用户）达到 1 次后，任何人都不能再从该 IP 投票
        $activity = $this->makeActivity(['votes_per_user' => 10, 'ip_limit' => 1]);
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser();

        $first = $this->service->submitVote($activity, [$optionA], [], $user, '6.6.6.6');
        $this->assertSame(0, $first['code']);

        $guest = $this->service->submitVote($activity, [$optionA], [], null, '6.6.6.6');
        $this->assertSame(40001, $guest['code']);
        $this->assertSame('该IP的投票次数已达上限', $guest['message']);
    }

    public function test_投票批次号按提交次数递增(): void
    {
        $activity = $this->makeActivity(['votes_per_user' => 3]);
        [$optionA, $optionB] = $this->makeOptions($activity);
        $user = $this->createUser();

        $first = $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');
        $second = $this->service->submitVote($activity, [$optionB], [], $user, '1.1.1.1');
        $third = $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');

        $this->assertSame(1, $first['data']['vote_no']);
        $this->assertSame(2, $second['data']['vote_no']);
        $this->assertSame(3, $third['data']['vote_no']);

        $voteNos = PollingRecord::where('activity_id', $activity->id)
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('vote_no')
            ->sort()
            ->values()
            ->all();
        $this->assertSame([1, 2, 3], $voteNos);
    }

    public function test_统计_排序投票Borda计分与名次分布(): void
    {
        $activity = $this->makeActivity(['type' => PollingActivity::TYPE_RANKING]);
        [$optionA, $optionB, $optionC] = $this->makeOptions($activity);
        $user = $this->createUser();

        // 用户一：A > B > C
        $this->service->submitVote($activity, [$optionA, $optionB, $optionC], [], $user, '1.1.1.1');
        // 游客：A > C > B
        $this->service->submitVote($activity, [$optionA, $optionC, $optionB], [], null, '7.7.7.7');

        $stats = $this->service->getStats($activity);

        $this->assertSame('ranking', $stats['type']);
        $this->assertSame(6, $stats['total_records']);
        // 参与人次：两个批次去重后为 2
        $this->assertSame(2, $stats['total_votes']);

        // 选项按得分降序排列：A（2+2=4分）居首
        $this->assertSame($optionA, $stats['options'][0]['id']);
        $this->assertSame(4, $stats['options'][0]['score']);
        $this->assertSame([1 => 2], $stats['options'][0]['rank_distribution']);

        // B 与 C 各得 1 分
        $optionStats = collect($stats['options'])->keyBy('id');
        $this->assertSame(1, $optionStats[$optionB]['score']);
        $this->assertSame(1, $optionStats[$optionC]['score']);
    }

    public function test_统计_多选占比与参与人次去重(): void
    {
        $activity = $this->makeActivity([
            'type' => PollingActivity::TYPE_MULTIPLE,
            'max_choices' => 2,
        ]);
        [$optionA, $optionB, $optionC] = $this->makeOptions($activity);
        $user = $this->createUser();

        // 用户一次勾选 A+B（1 个批次、2 条记录）
        $this->service->submitVote($activity, [$optionA, $optionB], [], $user, '1.1.1.1');
        // 游客一次勾选 A（1 个批次、1 条记录）
        $this->service->submitVote($activity, [$optionA], [], null, '8.8.8.8');

        $stats = $this->service->getStats($activity);

        $this->assertSame(3, $stats['total_records']);
        $this->assertSame(2, $stats['total_votes']);

        $optionStats = collect($stats['options'])->keyBy('id');
        $this->assertSame(2, $optionStats[$optionA]['count']);
        $this->assertSame(66.67, $optionStats[$optionA]['percent']);
        $this->assertSame(1, $optionStats[$optionB]['count']);
        $this->assertSame(33.33, $optionStats[$optionB]['percent']);
        $this->assertSame(0, $optionStats[$optionC]['count']);
        $this->assertSame(0.0, $optionStats[$optionC]['percent']);
    }

    public function test_结果可见性_实时可见与不公开(): void
    {
        $always = $this->makeActivity(['result_visibility' => PollingActivity::VISIBILITY_ALWAYS]);
        $this->makeOptions($always);
        $this->assertTrue($this->service->canViewResult($always, null, '1.1.1.1'));

        $never = $this->makeActivity(['result_visibility' => PollingActivity::VISIBILITY_NEVER]);
        $this->makeOptions($never);
        $this->assertFalse($this->service->canViewResult($never, null, '1.1.1.1'));
    }

    public function test_结果可见性_投票后可见(): void
    {
        $activity = $this->makeActivity(['result_visibility' => PollingActivity::VISIBILITY_AFTER_VOTE]);
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser();

        // 未投票前不可见
        $this->assertFalse($this->service->canViewResult($activity, $user, '1.1.1.1'));

        // 投票后立即可见
        $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');
        $this->assertTrue($this->service->canViewResult($activity, $user, '1.1.1.1'));

        // 换一个未投票的用户仍不可见
        $other = $this->createUser('other');
        $this->assertFalse($this->service->canViewResult($activity, $other, '2.2.2.2'));
    }

    public function test_结果可见性_结束后可见(): void
    {
        $activity = $this->makeActivity([
            'result_visibility' => PollingActivity::VISIBILITY_END,
            'end_time' => now()->addHour(),
        ]);
        $this->makeOptions($activity);

        // 进行中不可见
        $this->assertFalse($this->service->canViewResult($activity, null, '1.1.1.1'));

        // 到期后（status 未变、end_time 已过）运行时判定为已结束，结果可见
        $activity->update(['end_time' => now()->subMinute()]);
        $this->assertTrue($this->service->canViewResult($activity->refresh(), null, '1.1.1.1'));
    }

    public function test_投票提交成功后触发自定义动作钩子(): void
    {
        $activity = $this->makeActivity();
        [$optionA] = $this->makeOptions($activity);

        $fired = 0;
        $receivedRecords = null;
        app(HookManager::class)->registerAction('polling.vote.submitted', function ($hookActivity, $records) use (&$fired, &$receivedRecords) {
            $fired++;
            $receivedRecords = $records;
        });

        $result = $this->service->submitVote($activity, [$optionA], [], null, '1.1.1.1');

        $this->assertSame(0, $result['code']);
        $this->assertSame(1, $fired);
        $this->assertIsArray($receivedRecords);
        $this->assertCount(1, $receivedRecords);
        $this->assertSame($activity->id, $receivedRecords[0]->activity_id);
    }

    public function test_统计数据经过过滤钩子加工(): void
    {
        $activity = $this->makeActivity();
        $this->makeOptions($activity);

        app(HookManager::class)->registerFilter('polling.activity.stats', function (array $stats) {
            $stats['custom_marker'] = 'processed_by_filter';

            return $stats;
        });

        $stats = $this->service->getStats($activity);

        $this->assertSame('processed_by_filter', $stats['custom_marker']);
        $this->assertSame(0, $stats['total_votes']);
    }

    public function test_导出CSV带BOM头且匿名活动脱敏(): void
    {
        $activity = $this->makeActivity(['anonymous' => 1, 'result_visibility' => PollingActivity::VISIBILITY_ALWAYS]);
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser();

        $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');

        $csv = $this->service->exportStatsCsv($activity);

        // UTF-8 BOM 头，保证 Excel 打开中文不乱码
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        // 匿名活动：登录用户的用户名必须脱敏
        $this->assertStringNotContainsString($user->username, $csv);
        $this->assertStringContainsString('匿名', $csv);
        $this->assertStringContainsString($activity->title, $csv);
    }

    public function test_导出CSV非匿名活动保留用户名(): void
    {
        $activity = $this->makeActivity();
        [$optionA] = $this->makeOptions($activity);
        $user = $this->createUser('alice');

        $this->service->submitVote($activity, [$optionA], [], $user, '1.1.1.1');

        $csv = $this->service->exportStatsCsv($activity);

        $this->assertStringContainsString($user->username, $csv);
    }

    public function test_游客在匿名活动导出中显示为匿名游客(): void
    {
        $activity = $this->makeActivity(['anonymous' => 1]);
        [$optionA] = $this->makeOptions($activity);

        $this->service->submitVote($activity, [$optionA], [], null, '9.9.9.9');

        $csv = $this->service->exportStatsCsv($activity);

        $this->assertStringContainsString('匿名', $csv);
    }
}
