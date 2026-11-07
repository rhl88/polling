<?php

namespace App\Apps\CmsproPolling\Tests\Feature;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Tests\PollingTestCase;

/**
 * 前台公开端点集成测试。
 *
 * 走完整 HTTP 链路（路由 → web 中间件 → 控制器 → 服务），
 * 验证前台页面渲染与游客投票 API 的对外行为：
 * 1. 投票列表页：仅展示进行中的活动，草稿不可见
 * 2. 投票详情页：正常渲染与草稿 404
 * 3. 游客投票 API：成功投票、参数校验、活动不存在
 * 4. 统计 API：结果可见性控制（never 拦截 / always 放行）
 */
class HomePollingApiTest extends PollingTestCase
{
    public function test_前台列表页仅展示进行中的活动(): void
    {
        $active = $this->makeActivity(['title' => '进行中的公开投票']);
        $this->makeOptions($active);

        $draft = $this->makeActivity([
            'title' => '尚未发布的草稿投票',
            'status' => PollingActivity::STATUS_DRAFT,
        ]);
        $this->makeOptions($draft);

        $response = $this->get('/cmspro/polling');

        $response->assertOk()
            ->assertViewIs('cmspro.polling::Home.index')
            ->assertSee('进行中的公开投票')
            ->assertDontSee('尚未发布的草稿投票');
    }

    public function test_前台详情页正常渲染(): void
    {
        $activity = $this->makeActivity(['title' => '详情页测试投票']);
        $this->makeOptions($activity, ['详情选项一', '详情选项二']);

        $response = $this->get('/cmspro/polling/' . $activity->id);

        $response->assertOk()
            ->assertViewIs('cmspro.polling::Home.detail')
            ->assertSee('详情页测试投票')
            ->assertSee('详情选项一')
            ->assertSee('详情选项二');
    }

    public function test_详情页单选投票脚本为单选切换语义(): void
    {
        // 回归锚点：单选投票前端曾可多选高亮——toggleOption 无 single 分支，
        // 依次点击多个选项全部入列且显示「已选」，提交才被后端拦截。
        // 修复采用 radio 切换语义：点击新选项替换已选项，保证至多一项选中
        $activity = $this->makeActivity();
        $this->makeOptions($activity, ['选项一', '选项二']);

        $script = $this->get('/cmspro/polling/' . $activity->id)->getContent();

        $this->assertStringContainsString(
            "activityType === 'single'",
            $script,
            '单选交互脚本缺少 single 分支：点击新选项应替换已选项，防止多选'
        );
        $this->assertStringContainsString(
            'selected = [id];',
            $script,
            'single 分支应为 radio 切换语义（selected = [id] 直接替换），而非 push 追加'
        );
    }

    public function test_前台详情页草稿活动返回404(): void
    {
        $draft = $this->makeActivity([
            'title' => '尚未发布的草稿投票',
            'status' => PollingActivity::STATUS_DRAFT,
        ]);
        $this->makeOptions($draft);

        // CMSPRO 框架的 404 页面设计上返回 HTTP 200（规避 nginx fastcgi_intercept_errors
        // 拦截 404 状态码），此处断言渲染的是「页面不存在」提示页，且草稿内容不泄露
        $this->get('/cmspro/polling/' . $draft->id)
            ->assertOk()
            ->assertSee('页面不存在')
            ->assertDontSee('尚未发布的草稿投票');
    }

    public function test_游客通过API投票成功(): void
    {
        $activity = $this->makeActivity();
        [$optionA] = $this->makeOptions($activity);

        $response = $this->postJson('/cmspro/polling/api/vote/' . $activity->id, [
            'option_ids' => [$optionA],
        ]);

        $response->assertOk()
            ->assertJson(['code' => 0])
            ->assertJsonPath('data.vote_no', 1);

        $this->assertDatabaseHas('app_cmspro_polling_records', [
            'activity_id' => $activity->id,
            'option_id' => $optionA,
            'user_id' => 0,
            // 测试环境客户端 IP 为 127.0.0.1
            'ip' => '127.0.0.1',
        ]);
    }

    public function test_投票API缺少选项参数返回422(): void
    {
        $activity = $this->makeActivity();
        $this->makeOptions($activity);

        // CMSPRO 统一参数校验响应：HTTP 422 + ApiResponse 包装（code 40201，明细在 data）
        $response = $this->postJson('/cmspro/polling/api/vote/' . $activity->id, []);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 40201)
            ->assertJsonPath('message', '请求参数不合法');

        $errors = $response->json('data');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('option_ids', $errors);
    }

    public function test_投票API活动不存在返回40401(): void
    {
        $this->postJson('/cmspro/polling/api/vote/99999', [
            'option_ids' => [1],
        ])->assertOk()->assertJson(['code' => 40401]);
    }

    public function test_统计API结果不公开时被拦截(): void
    {
        $activity = $this->makeActivity(['result_visibility' => PollingActivity::VISIBILITY_NEVER]);
        $this->makeOptions($activity);

        $this->getJson('/cmspro/polling/api/stats/' . $activity->id)
            ->assertOk()
            ->assertJson(['code' => 40301])
            ->assertJsonPath('message', '当前投票结果暂不可见');
    }

    public function test_统计API结果实时可见时返回统计数据(): void
    {
        $activity = $this->makeActivity(['result_visibility' => PollingActivity::VISIBILITY_ALWAYS]);
        [$optionA] = $this->makeOptions($activity);

        // 先投一票再查统计
        $this->postJson('/cmspro/polling/api/vote/' . $activity->id, [
            'option_ids' => [$optionA],
        ])->assertOk();

        $response = $this->getJson('/cmspro/polling/api/stats/' . $activity->id);

        $response->assertOk()
            ->assertJson(['code' => 0])
            ->assertJsonPath('data.total_votes', 1)
            ->assertJsonPath('data.options.0.id', $optionA);
    }
}
