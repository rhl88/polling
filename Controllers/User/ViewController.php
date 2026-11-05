<?php

namespace App\Apps\CmsproPolling\Controllers\User;

use App\Apps\CmsproPolling\Services\ActivityService;
use Illuminate\Routing\Controller;

class ViewController extends Controller
{
    /**
     * 我的投票列表页
     */
    public function index()
    {
        return view('cmspro.polling::User.index');
    }

    /**
     * 发起投票页
     */
    public function create()
    {
        // 预填后台「投票设置」配置项（数据库直读，未配置时回退默认值）
        return view('cmspro.polling::User.form', [
            'activityId' => null,
            'defaultVotesPerUser' => (int) ActivityService::getConfig('default_votes_per_user', 1),
            'defaultIpLimit' => (int) ActivityService::getConfig('ip_limit', 0),
        ]);
    }

    /**
     * 编辑投票页
     */
    public function edit($id)
    {
        return view('cmspro.polling::User.form', [
            'activityId' => (int) $id,
        ]);
    }

    /**
     * 我的参与记录页
     */
    public function records()
    {
        return view('cmspro.polling::User.records');
    }
}
