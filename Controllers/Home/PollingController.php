<?php

namespace App\Apps\CmsproPolling\Controllers\Home;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Services\ActivityService;
use App\Apps\CmsproPolling\Services\VoteService;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PollingController extends Controller
{
    public function __construct(
        protected ActivityService $activityService,
        protected VoteService $voteService
    ) {
    }

    /**
     * 前台投票列表页
     */
    public function index()
    {
        $activities = PollingActivity::where('status', PollingActivity::STATUS_ACTIVE)
            ->withCount('records')
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate(12);

        return view('cmspro.polling::Home.index', [
            'activities' => $activities,
            'typeMap' => ActivityService::TYPE_MAP,
        ]);
    }

    /**
     * 前台投票详情页
     */
    public function detail($id)
    {
        $activity = PollingActivity::with('options')
            ->where('status', '!=', PollingActivity::STATUS_DRAFT)
            ->find((int) $id);

        if (!$activity) {
            abort(404);
        }

        $user = auth('web')->user();
        $ip = $this->getClientIp();

        $canViewResult = $this->voteService->canViewResult($activity, $user, $ip);
        $stats = $canViewResult ? $this->voteService->getStats($activity) : null;
        $hasVoted = $this->voteService->hasVoted($activity, $user, $ip);

        return view('cmspro.polling::Home.detail', [
            'activity' => $activity,
            'typeMap' => ActivityService::TYPE_MAP,
            'statusMap' => ActivityService::STATUS_MAP,
            'user' => $user,
            'canViewResult' => $canViewResult,
            'hasVoted' => $hasVoted,
            'stats' => $stats,
        ]);
    }

    /**
     * 提交投票（游客可投票，活动级控制是否需要登录）
     */
    public function vote(Request $request, $id)
    {
        $activity = PollingActivity::find((int) $id);
        if (!$activity) {
            return response()->json(ApiResponse::error(40401, '投票活动不存在'));
        }

        $data = $request->validate([
            'option_ids' => 'required|array|min:1',
            'option_ids.*' => 'integer|min:1',
            'ranks' => 'nullable|array',
        ]);

        $user = auth('web')->user();

        return response()->json(
            $this->voteService->submitVote(
                $activity,
                $data['option_ids'],
                $data['ranks'] ?? [],
                $user,
                $this->getClientIp()
            )
        );
    }

    /**
     * 获取活动实时统计（受结果可见性控制）
     */
    public function stats($id)
    {
        $activity = PollingActivity::find((int) $id);
        if (!$activity) {
            return response()->json(ApiResponse::error(40401, '投票活动不存在'));
        }

        $user = auth('web')->user();

        if (!$this->voteService->canViewResult($activity, $user, $this->getClientIp())) {
            return response()->json(ApiResponse::error(40301, '当前投票结果暂不可见'));
        }

        return response()->json(
            ApiResponse::success($this->voteService->getStats($activity))
        );
    }

    /**
     * 获取客户端IP
     */
    protected function getClientIp(): string
    {
        return (string) request()->ip();
    }
}
