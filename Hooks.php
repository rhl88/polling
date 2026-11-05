<?php

namespace App\Apps\CmsproPolling;

use App\Services\HookManager;

/**
 * 投票系统钩子注册入口
 *
 * 本应用对外提供的自定义钩子点（由 Service 层触发，其他应用可监听）：
 * - Action `polling.vote.submitted`：投票提交成功后触发，参数：PollingActivity $activity, array $records
 * - Filter `polling.activity.stats`：统计数据返回前触发，参数：array $stats, PollingActivity $activity
 *
 * 本应用当前无需监听系统钩子；如需监听（如 user.after_register 等），
 * 请在 register() 中按下方示例注册，第 4 参数必须传 app_id：
 *
 * $hooks->registerAction('user.after_register', [$this, 'onUserRegistered'], 10, 'cmspro.polling');
 */
class Hooks
{
    public function register(HookManager $hooks): void
    {
        // 当前无系统钩子监听需求，保留注册入口供后续扩展
    }
}
