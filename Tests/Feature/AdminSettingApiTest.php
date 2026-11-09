<?php

namespace App\Apps\CmsproPolling\Tests\Feature;

use App\Apps\CmsproPolling\Install;
use App\Apps\CmsproPolling\Tests\PollingTestCase;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * 投票设置后台功能测试。
 *
 * 覆盖场景：
 * 1. 菜单声明——manifest 含「投票设置」独立子菜单；
 * 2. 设置页面——管理员登录后可访问；
 * 3. 设置接口——读取 pollings_settings 配置组的三项配置；
 * 4. 更新接口——白名单内配置写入并立即生效；
 * 5. 更新接口——白名单外的配置键拒绝写入；
 * 6. 更新接口——配置值类型校验（数字非负整数、开关 0/1）。
 */
class AdminSettingApiTest extends PollingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 复现安装后状态：config_items 中的 polling_settings 配置组
        (new Install())->install();
    }

    /**
     * 构造超级管理员（polling.permission 中间件对超管放行）
     */
    protected function createSuperAdmin(): AdminUser
    {
        $user = AdminUser::create([
            'username' => 'polling_admin_' . uniqid(),
            'name' => '投票管理员',
            'password' => 'password',
            'status' => 1,
        ]);

        $role = AdminRole::create([
            'name' => '超管角色',
            'code' => 'super_admin',
            'status' => 1,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_菜单声明包含投票设置子菜单(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(app_path('Apps/CmsproPolling/manifest.json')),
            true
        );

        $children = $manifest['menus'][0]['children'] ?? [];
        $codes = array_column($children, 'code');

        $this->assertContains('admin_cmspro_polling_settings', $codes, 'manifest 缺少「投票设置」子菜单声明');

        $settingsMenu = collect($children)->firstWhere('code', 'admin_cmspro_polling_settings');
        $this->assertSame('/admin/cmspro/polling/settings', $settingsMenu['path'], '设置菜单 path 应指向设置页面路由');
        $this->assertSame('投票设置', $settingsMenu['title']);
    }

    public function test_设置页面管理员登录后可访问(): void
    {
        $super = $this->createSuperAdmin();

        $response = $this->actingAs($super, 'admin')
            ->get('/admin/cmspro/polling/settings');

        $response->assertOk();
        $response->assertViewIs('cmspro.polling::Admin.settings.index');
    }

    public function test_设置接口返回投票配置项(): void
    {
        $super = $this->createSuperAdmin();

        $response = $this->actingAs($super, 'admin')
            ->getJson('/admin/cmspro/polling/api/settings');

        $response->assertOk();
        $response->assertJson(['code' => 0]);

        $data = $response->json('data');
        $this->assertSame('1', (string) $data['default_votes_per_user']);
        $this->assertSame('0', (string) $data['ip_limit']);
        $this->assertSame('1', (string) $data['allow_user_create']);
    }

    public function test_更新设置写入配置表并立即生效(): void
    {
        $super = $this->createSuperAdmin();

        $response = $this->actingAs($super, 'admin')
            ->putJson('/admin/cmspro/polling/api/settings', [
                'settings' => [
                    'default_votes_per_user' => 3,
                    'ip_limit' => 5,
                    'allow_user_create' => 0,
                ],
            ]);

        $response->assertOk();
        $response->assertJson(['code' => 0]);

        $this->assertSame('3', (string) DB::table('config_items')->where('code', 'app_cmspro_polling_default_votes_per_user')->value('value'));
        $this->assertSame('5', (string) DB::table('config_items')->where('code', 'app_cmspro_polling_ip_limit')->value('value'));
        $this->assertSame('0', (string) DB::table('config_items')->where('code', 'app_cmspro_polling_allow_user_create')->value('value'));

        // 再读接口应返回新值（立即生效）
        $get = $this->actingAs($super, 'admin')
            ->getJson('/admin/cmspro/polling/api/settings');

        $this->assertSame(3, (int) $get->json('data.default_votes_per_user'));
        $this->assertSame(5, (int) $get->json('data.ip_limit'));
        $this->assertSame(0, (int) $get->json('data.allow_user_create'));
    }

    public function test_更新设置拒绝白名单外的配置键(): void
    {
        $super = $this->createSuperAdmin();

        $response = $this->actingAs($super, 'admin')
            ->putJson('/admin/cmspro/polling/api/settings', [
                'settings' => [
                    'default_votes_per_user' => 2,
                    'hacked_key' => 'evil',
                ],
            ]);

        $response->assertStatus(422);
        $this->assertSame(40001, $response->json('code'));

        // 白名单内的键也不应落库（整单拒绝）
        $this->assertSame('1', (string) DB::table('config_items')->where('code', 'app_cmspro_polling_default_votes_per_user')->value('value'));
        // 白名单外的键不得写入
        $this->assertDatabaseMissing('config_items', ['code' => 'app_cmspro_polling_hacked_key']);
    }

    public function test_更新设置校验配置值类型(): void
    {
        $super = $this->createSuperAdmin();

        // 数字项不允许负数
        $response = $this->actingAs($super, 'admin')
            ->putJson('/admin/cmspro/polling/api/settings', [
                'settings' => ['ip_limit' => -1],
            ]);
        $response->assertStatus(422);

        // 开关项仅允许 0/1
        $response = $this->actingAs($super, 'admin')
            ->putJson('/admin/cmspro/polling/api/settings', [
                'settings' => ['allow_user_create' => 5],
            ]);
        $response->assertStatus(422);

        // 原值保持不变
        $this->assertSame('0', (string) DB::table('config_items')->where('code', 'app_cmspro_polling_ip_limit')->value('value'));
        $this->assertSame('1', (string) DB::table('config_items')->where('code', 'app_cmspro_polling_allow_user_create')->value('value'));
    }
}
