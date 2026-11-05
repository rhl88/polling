<?php

namespace App\Apps\CmsproPolling\Controllers\Admin;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Services\ActivityService;
use App\Apps\CmsproPolling\Services\VoteService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActivityController extends Controller
{
    public function __construct(
        protected ActivityService $activityService,
        protected VoteService $voteService
    ) {
    }

    /**
     * 活动列表
     */
    public function index(Request $request)
    {
        return response()->json(
            $this->activityService->getList($request->all())
        );
    }

    /**
     * 活动详情
     */
    public function show($id)
    {
        return response()->json(
            $this->activityService->show((int) $id)
        );
    }

    /**
     * 创建活动
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|string|in:single,multiple,ranking',
            'max_choices' => 'nullable|integer|min:1',
            'start_time' => 'nullable|string|max:30',
            'end_time' => 'nullable|string|max:30',
            'status' => 'nullable|integer|in:0,1,2',
            'require_login' => 'nullable|integer|in:0,1',
            'votes_per_user' => 'nullable|integer|min:1',
            'ip_limit' => 'nullable|integer|min:0',
            'anonymous' => 'nullable|integer|in:0,1',
            'result_visibility' => 'nullable|string|in:always,after_vote,end,never',
            'sort' => 'nullable|integer|min:0',
            'options' => 'required|array|min:2',
            'options.*.id' => 'nullable|integer|min:1',
            'options.*.title' => 'required|string|max:200',
            'options.*.description' => 'nullable|string|max:500',
            'options.*.image' => 'nullable|string|max:255',
            'options.*.sort' => 'nullable|integer|min:0',
        ]);

        return response()->json(
            $this->activityService->store($data)
        );
    }

    /**
     * 更新活动
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|string|in:single,multiple,ranking',
            'max_choices' => 'nullable|integer|min:1',
            'start_time' => 'nullable|string|max:30',
            'end_time' => 'nullable|string|max:30',
            'status' => 'nullable|integer|in:0,1,2',
            'require_login' => 'nullable|integer|in:0,1',
            'votes_per_user' => 'nullable|integer|min:1',
            'ip_limit' => 'nullable|integer|min:0',
            'anonymous' => 'nullable|integer|in:0,1',
            'result_visibility' => 'nullable|string|in:always,after_vote,end,never',
            'sort' => 'nullable|integer|min:0',
            'options' => 'nullable|array|min:2',
            'options.*.id' => 'nullable|integer|min:1',
            'options.*.title' => 'required|string|max:200',
            'options.*.description' => 'nullable|string|max:500',
            'options.*.image' => 'nullable|string|max:255',
            'options.*.sort' => 'nullable|integer|min:0',
        ]);

        return response()->json(
            $this->activityService->update((int) $id, $data)
        );
    }

    /**
     * 删除活动
     */
    public function destroy($id)
    {
        return response()->json(
            $this->activityService->destroy((int) $id)
        );
    }

    /**
     * 更新活动状态
     */
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|integer|in:0,1,2',
        ]);

        return response()->json(
            $this->activityService->updateStatus((int) $id, (int) $data['status'])
        );
    }

    /**
     * 活动统计结果
     */
    public function stats($id)
    {
        $activity = PollingActivity::find((int) $id);
        if (!$activity) {
            return response()->json(ApiResponse::error(40401, '投票活动不存在'));
        }

        return response()->json(
            ApiResponse::success($this->voteService->getStats($activity))
        );
    }

    /**
     * 导出统计报告 CSV
     */
    public function exportStats($id)
    {
        $activity = PollingActivity::find((int) $id);
        if (!$activity) {
            return response()->json(ApiResponse::error(40401, '投票活动不存在'));
        }

        $csv = $this->voteService->exportStatsCsv($activity);
        $filename = '投票统计_' . $activity->id . '_' . date('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
        ]);
    }
}
