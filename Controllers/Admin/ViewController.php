<?php

namespace App\Apps\CmsproPolling\Controllers\Admin;

use App\Apps\CmsproPolling\Models\PollingActivity;
use Illuminate\Routing\Controller;

class ViewController extends Controller
{
    /**
     * 活动管理列表页
     */
    public function activityIndex()
    {
        return view('cmspro.polling::Admin.activities.index');
    }

    /**
     * 活动表单页（创建/编辑共用）
     */
    public function activityForm($id = null)
    {
        return view('cmspro.polling::Admin.activities.form', [
            'activityId' => $id,
        ]);
    }

    /**
     * 活动统计详情页
     */
    public function activityStats($id)
    {
        $activity = PollingActivity::findOrFail($id);

        return view('cmspro.polling::Admin.activities.stats', [
            'activityId' => $activity->id,
            'activityTitle' => $activity->title,
        ]);
    }

    /**
     * 投票记录页
     */
    public function recordIndex()
    {
        return view('cmspro.polling::Admin.records.index');
    }
}
