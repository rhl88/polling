<?php

namespace App\Apps\CmsproPolling\Controllers\User;

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
     * 我发起的投票列表
     */
    public function index(Request $request)
    {
        $userId = (int) auth('web')->id();

        return response()->json(
            $this->activityService->getUserOwnList($userId, $request->all())
        );
    }

    /**
     * 投票详情（仅限本人创建的）
     */
    public function show($id)
    {
        $activity = PollingActivity::where('id', (int) $id)
            ->where('user_id', (int) auth('web')->id())
            ->first();

        if (!$activity) {
            return response()->json(ApiResponse::error(40401, '投票活动不存在'));
        }

        return response()->json(
            $this->activityService->show((int) $id)
        );
    }

    /**
     * 发起投票
     */
    public function store(Request $request)
    {
        // 全局配置：是否允许用户端发起投票（后台「投票设置」配置项，数据库直读）
        $allowUserCreate = (string) ActivityService::getConfig('allow_user_create', '1') === '1';
        if (!$allowUserCreate) {
            return response()->json(ApiResponse::error(40301, '管理员已关闭用户端发起投票功能'));
        }

        $data = $this->validateActivityPayload($request);

        return response()->json(
            $this->activityService->store($data, (int) auth('web')->id())
        );
    }

    /**
     * 更新投票（仅限本人创建的）
     */
    public function update(Request $request, $id)
    {
        $data = $this->validateActivityPayload($request);

        return response()->json(
            $this->activityService->update((int) $id, $data, (int) auth('web')->id())
        );
    }

    /**
     * 删除投票（仅限本人创建的）
     */
    public function destroy($id)
    {
        return response()->json(
            $this->activityService->destroy((int) $id, (int) auth('web')->id())
        );
    }

    /**
     * 更新投票状态（仅限本人创建的）
     */
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|integer|in:0,1,2',
        ]);

        return response()->json(
            $this->activityService->updateStatus((int) $id, (int) $data['status'], (int) auth('web')->id())
        );
    }

    /**
     * 我的投票统计结果（仅限本人创建的）
     */
    public function stats(Request $request, $id)
    {
        $activity = PollingActivity::where('id', (int) $id)
            ->where('user_id', (int) auth('web')->id())
            ->first();

        if (!$activity) {
            return response()->json(ApiResponse::error(40401, '投票活动不存在'));
        }

        return response()->json(
            ApiResponse::success($this->voteService->getStats($activity))
        );
    }

    /**
     * 用户端活动数据验证
     */
    protected function validateActivityPayload(Request $request): array
    {
        return $request->validate([
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
            'options.*.sort' => 'nullable|integer|min:0',
        ]);
    }
}
