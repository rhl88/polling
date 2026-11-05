<?php

namespace App\Apps\CmsproPolling\Tests;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Models\PollingOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 投票系统应用测试基类。
 *
 * 统一承载：
 * 1. 清库前安全护栏——migrate:fresh 会 DROP 当前连接库的全部表，
 *    必须先确认连接为 sqlite :memory: 内存库（phpunit.xml 钉死），
 *    从代码层杜绝 phpunit --no-configuration 绕过配置清空真实业务库；
 * 2. 应用自带迁移——activities/options/records 三表不在框架
 *    database/migrations 内，需在框架迁移后补跑应用迁移；
 * 3. 通用测试数据构造助手。
 */
abstract class PollingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 内存库中 apps 表为空，应用路由与视图不会被自动注册，
        // 这里手动注册应用的 ServiceProvider 以启用待测路由与视图
        $this->app->register(\App\Apps\CmsproPolling\ServiceProvider::class);

        // 开发环境的 DevViewPathBanner 中间件（.env APP_DEV_MODE=true）会重写响应内容，
        // 导致视图断言拿到的 original 不再是 View 实例，测试内关闭以保证断言稳定
        config(['app.dev_mode' => false]);
    }

    /**
     * 清库前安全护栏：必须在 migrate:fresh 之前拦截。
     */
    protected function beforeRefreshingDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $this->fail(
                "检测到当前数据库连接为 [{$connection} => {$database}]，并非 sqlite :memory: 内存库。"
                . '为防止 RefreshDatabase 的 migrate:fresh 清空真实业务库，已中止测试。'
                . '请改用 `php artisan test` 运行（会加载 code/phpunit.xml 锁定内存库），'
                . '严禁使用 phpunit --no-configuration 绕过配置。'
            );
        }
    }

    /**
     * 应用表（activities/options/records）由应用自带迁移创建，
     * 框架 RefreshDatabase 只覆盖 database/migrations，此处补跑。
     *
     * migrate 命令按 migrations 表记录幂等执行，重复调用为空操作。
     */
    protected function afterRefreshingDatabase(): void
    {
        Artisan::call('migrate', [
            '--path' => app_path('Apps/CmsproPolling/Migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    /**
     * 构造测试用户
     */
    protected function createUser(string $prefix = 'voter'): User
    {
        return User::create([
            'username' => $prefix . '_' . uniqid(),
            'password' => bcrypt('123456'),
            'nickname' => $prefix . '用户',
            'status' => 1,
        ]);
    }

    /**
     * 构造投票活动。
     *
     * 默认形态：进行中的单选投票、允许游客投票、每人限投 1 次、
     * 无 IP 限制、结果实时可见、时间窗口覆盖当前时刻。
     */
    protected function makeActivity(array $overrides = []): PollingActivity
    {
        $data = array_merge([
            'title' => '测试投票活动',
            'description' => '用于单元测试的投票活动',
            'type' => PollingActivity::TYPE_SINGLE,
            'max_choices' => 1,
            'start_time' => now()->subDay(),
            'end_time' => now()->addDay(),
            'status' => PollingActivity::STATUS_ACTIVE,
            'require_login' => 0,
            'votes_per_user' => 1,
            'ip_limit' => 0,
            'anonymous' => 0,
            'result_visibility' => PollingActivity::VISIBILITY_ALWAYS,
            'user_id' => 0,
            'sort' => 0,
        ], $overrides);

        return PollingActivity::create($data);
    }

    /**
     * 为活动追加选项，返回选项ID列表（顺序与传入标题一致）
     */
    protected function makeOptions(PollingActivity $activity, array $titles = ['选项甲', '选项乙', '选项丙']): array
    {
        $optionIds = [];

        foreach ($titles as $index => $title) {
            $optionIds[] = PollingOption::create([
                'activity_id' => $activity->id,
                'title' => $title,
                'description' => '',
                'image' => '',
                'sort' => $index + 1,
            ])->id;
        }

        return $optionIds;
    }
}
