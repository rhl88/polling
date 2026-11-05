<?php

namespace App\Apps\CmsproPolling\Controllers\User;

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
        return view('cmspro.polling::User.form', [
            'activityId' => null,
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
