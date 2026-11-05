<?php

namespace App\Apps\CmsproPolling\Services;

use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Models\PollingOption;
use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivityService
{
    /**
     * 投票类型选项
     */
    public const TYPE_MAP = [
        PollingActivity::TYPE_SINGLE => '单选投票',
        PollingActivity::TYPE_MULTIPLE => '多选投票',
        PollingActivity::TYPE_RANKING => '排序投票',
    ];

    /**
     * 状态选项
     */
    public const STATUS_MAP = [
        PollingActivity::STATUS_DRAFT => '草稿',
        PollingActivity::STATUS_ACTIVE => '进行中',
        PollingActivity::STATUS_ENDED => '已结束',
    ];

    /**
     * 后台活动列表
     */
    public function getList(array $params): array
    {
        $query = PollingActivity::query();

        if (!empty($params['keyword'])) {
            $keyword = trim((string) $params['keyword']);
            $query->where('title', 'like', "%{$keyword}%");
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (!empty($params['start_date'])) {
            $query->whereDate('create_time', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->whereDate('create_time', '<=', $params['end_date']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $items = $query->withCount('records')
            ->orderBy('sort')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items->through(function (PollingActivity $activity) {
            return $this->formatActivity($activity);
        });

        return ApiResponse::paginate($items);
    }

    /**
     * 用户端：我发起的投票列表
     */
    public function getUserOwnList(int $userId, array $params): array
    {
        $query = PollingActivity::where('user_id', $userId);

        if (!empty($params['keyword'])) {
            $keyword = trim((string) $params['keyword']);
            $query->where('title', 'like', "%{$keyword}%");
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $items = $query->withCount('records')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items->through(function (PollingActivity $activity) {
            return $this->formatActivity($activity);
        });

        return ApiResponse::paginate($items);
    }

    /**
     * 活动详情（含选项）
     */
    public function show(int $id): array
    {
        $activity = PollingActivity::with('options')->find($id);
        if (!$activity) {
            return ApiResponse::error(40401, '投票活动不存在');
        }

        return ApiResponse::success($this->formatActivity($activity, true));
    }

    /**
     * 创建活动（含选项）
     *
     * @param int $userId 创建者用户ID，0-后台管理员
     */
    public function store(array $data, int $userId = 0): array
    {
        $error = $this->validateActivityData($data);
        if ($error !== null) {
            return ApiResponse::error(40001, $error);
        }

        $optionsError = $this->validateOptions($data['options'] ?? [], $data['type'], (int) $data['max_choices']);
        if ($optionsError !== null) {
            return ApiResponse::error(40001, $optionsError);
        }

        try {
            $activity = DB::transaction(function () use ($data, $userId) {
                $activity = PollingActivity::create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'type' => $data['type'],
                    'max_choices' => (int) ($data['max_choices'] ?? 1),
                    'start_time' => $data['start_time'] ?: null,
                    'end_time' => $data['end_time'] ?: null,
                    'status' => (int) ($data['status'] ?? PollingActivity::STATUS_DRAFT),
                    'require_login' => (int) ($data['require_login'] ?? 0),
                    'votes_per_user' => (int) ($data['votes_per_user'] ?? 1),
                    'ip_limit' => (int) ($data['ip_limit'] ?? 0),
                    'anonymous' => (int) ($data['anonymous'] ?? 0),
                    'result_visibility' => $data['result_visibility'] ?? PollingActivity::VISIBILITY_ALWAYS,
                    'user_id' => $userId,
                    'sort' => (int) ($data['sort'] ?? 0),
                ]);

                $this->saveOptions($activity, $data['options'] ?? []);

                return $activity;
            });
        } catch (\Throwable $e) {
            Log::error('投票活动创建失败', ['error' => $e->getMessage()]);
            return ApiResponse::error(50001, '创建失败：' . $e->getMessage());
        }

        return ApiResponse::success($this->formatActivity($activity->load('options'), true), '创建成功');
    }

    /**
     * 更新活动（含选项整体替换）
     */
    public function update(int $id, array $data, ?int $limitUserId = null): array
    {
        $activity = PollingActivity::find($id);
        if (!$activity) {
            return ApiResponse::error(40401, '投票活动不存在');
        }

        // 用户端仅允许操作自己的活动
        if ($limitUserId !== null && (int) $activity->user_id !== $limitUserId) {
            return ApiResponse::error(40301, '无权操作他人创建的投票');
        }

        $error = $this->validateActivityData($data, $activity);
        if ($error !== null) {
            return ApiResponse::error(40001, $error);
        }

        if (array_key_exists('options', $data)) {
            $optionsError = $this->validateOptions($data['options'], $data['type'], (int) $data['max_choices']);
            if ($optionsError !== null) {
                return ApiResponse::error(40001, $optionsError);
            }
        }

        // 已产生投票记录时不允许更换投票类型（避免历史数据计分错乱）
        if (isset($data['type']) && $data['type'] !== $activity->type && $activity->records()->exists()) {
            return ApiResponse::error(40001, '该活动已有投票记录，不允许更换投票类型');
        }

        try {
            DB::transaction(function () use ($activity, $data) {
                $activity->update([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? '',
                    'type' => $data['type'],
                    'max_choices' => (int) ($data['max_choices'] ?? $activity->max_choices),
                    'start_time' => $data['start_time'] ?: null,
                    'end_time' => $data['end_time'] ?: null,
                    'status' => (int) ($data['status'] ?? $activity->status),
                    'require_login' => (int) ($data['require_login'] ?? 0),
                    'votes_per_user' => (int) ($data['votes_per_user'] ?? $activity->votes_per_user),
                    'ip_limit' => (int) ($data['ip_limit'] ?? $activity->ip_limit),
                    'anonymous' => (int) ($data['anonymous'] ?? 0),
                    'result_visibility' => $data['result_visibility'] ?? $activity->result_visibility,
                    'sort' => (int) ($data['sort'] ?? $activity->sort),
                ]);

                if (array_key_exists('options', $data)) {
                    $this->saveOptions($activity, $data['options']);
                }
            });
        } catch (\Throwable $e) {
            Log::error('投票活动更新失败', ['error' => $e->getMessage()]);
            return ApiResponse::error(50001, '更新失败：' . $e->getMessage());
        }

        return ApiResponse::success($this->formatActivity($activity->refresh()->load('options'), true), '更新成功');
    }

    /**
     * 更新活动状态
     */
    public function updateStatus(int $id, int $status, ?int $limitUserId = null): array
    {
        $activity = PollingActivity::find($id);
        if (!$activity) {
            return ApiResponse::error(40401, '投票活动不存在');
        }

        if ($limitUserId !== null && (int) $activity->user_id !== $limitUserId) {
            return ApiResponse::error(40301, '无权操作他人创建的投票');
        }

        if (!array_key_exists($status, self::STATUS_MAP)) {
            return ApiResponse::error(40001, '无效的活动状态');
        }

        $activity->update(['status' => $status]);

        return ApiResponse::success($this->formatActivity($activity->refresh()), '状态更新成功');
    }

    /**
     * 删除活动（级联删除选项与记录）
     */
    public function destroy(int $id, ?int $limitUserId = null): array
    {
        $activity = PollingActivity::find($id);
        if (!$activity) {
            return ApiResponse::error(40401, '投票活动不存在');
        }

        if ($limitUserId !== null && (int) $activity->user_id !== $limitUserId) {
            return ApiResponse::error(40301, '无权操作他人创建的投票');
        }

        try {
            DB::transaction(function () use ($activity) {
                PollingRecord::where('activity_id', $activity->id)->delete();
                PollingOption::where('activity_id', $activity->id)->delete();
                $activity->delete();
            });
        } catch (\Throwable $e) {
            Log::error('投票活动删除失败', ['error' => $e->getMessage()]);
            return ApiResponse::error(50001, '删除失败：' . $e->getMessage());
        }

        return ApiResponse::success(null, '删除成功');
    }

    /**
     * 后台投票记录列表
     */
    public function getRecordList(array $params): array
    {
        $query = PollingRecord::query()->with(['option', 'user']);

        if (!empty($params['activity_id'])) {
            $query->where('activity_id', (int) $params['activity_id']);
        }

        if (isset($params['option_id']) && $params['option_id'] !== '') {
            $query->where('option_id', (int) $params['option_id']);
        }

        if (isset($params['user_id']) && $params['user_id'] !== '') {
            $query->where('user_id', (int) $params['user_id']);
        }

        if (!empty($params['ip'])) {
            $query->where('ip', 'like', '%' . trim((string) $params['ip']) . '%');
        }

        if (!empty($params['start_date'])) {
            $query->whereDate('create_time', '>=', $params['start_date']);
        }
        if (!empty($params['end_date'])) {
            $query->whereDate('create_time', '<=', $params['end_date']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $items = $query->orderByDesc('id')->paginate($perPage);

        $items->through(function (PollingRecord $record) {
            return [
                'id' => $record->id,
                'activity_id' => $record->activity_id,
                'activity_title' => $record->activity?->title,
                'option_id' => $record->option_id,
                'option_title' => $record->option?->title,
                'username' => $record->user_id > 0 ? ($record->user?->username ?? '-') : '游客',
                'nickname' => $record->user_id > 0 ? ($record->user?->nickname ?? '-') : '-',
                'vote_no' => $record->vote_no,
                'rank' => $record->rank,
                'ip' => $record->ip,
                'create_time' => $record->create_time,
            ];
        });

        return ApiResponse::paginate($items);
    }

    /**
     * 活动数据验证，返回错误信息（null 表示通过）
     */
    protected function validateActivityData(array $data, ?PollingActivity $activity = null): ?string
    {
        if (trim((string) ($data['title'] ?? '')) === '') {
            return '活动标题不能为空';
        }

        if (mb_strlen((string) $data['title']) > 200) {
            return '活动标题不能超过200字';
        }

        $type = $data['type'] ?? '';
        if (!isset(self::TYPE_MAP[$type])) {
            return '无效的投票类型';
        }

        if ($type === PollingActivity::TYPE_MULTIPLE) {
            $maxChoices = (int) ($data['max_choices'] ?? 0);
            if ($maxChoices < 2) {
                return '多选投票最多可选数不能少于2';
            }
        }

        $status = (int) ($data['status'] ?? PollingActivity::STATUS_DRAFT);
        if (!array_key_exists($status, self::STATUS_MAP)) {
            return '无效的活动状态';
        }

        $visibility = $data['result_visibility'] ?? '';
        $validVisibility = [
            PollingActivity::VISIBILITY_ALWAYS,
            PollingActivity::VISIBILITY_AFTER_VOTE,
            PollingActivity::VISIBILITY_END,
            PollingActivity::VISIBILITY_NEVER,
        ];
        if (!in_array($visibility, $validVisibility, true)) {
            return '无效的结果可见性设置';
        }

        if ((int) ($data['votes_per_user'] ?? 1) < 1) {
            return '每人限投次数不能少于1次';
        }

        if ((int) ($data['ip_limit'] ?? 0) < 0) {
            return '同IP投票限制不能为负数';
        }

        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;
        if ($startTime && $endTime && strtotime($endTime) <= strtotime($startTime)) {
            return '结束时间必须晚于开始时间';
        }

        return null;
    }

    /**
     * 选项数据验证，返回错误信息（null 表示通过）
     */
    protected function validateOptions(array $options, string $type, int $maxChoices): ?string
    {
        $validOptions = array_values(array_filter($options, function ($option) {
            return trim((string) ($option['title'] ?? '')) !== '';
        }));

        if (count($validOptions) < 2) {
            return '至少需要2个有效选项';
        }

        foreach ($validOptions as $option) {
            if (mb_strlen((string) $option['title']) > 200) {
                return '选项标题不能超过200字';
            }
        }

        if ($type === PollingActivity::TYPE_MULTIPLE && count($validOptions) < $maxChoices) {
            return '选项数量不能少于多选最多可选数';
        }

        return null;
    }

    /**
     * 保存活动选项（整体替换策略）
     */
    protected function saveOptions(PollingActivity $activity, array $options): void
    {
        $validOptions = array_values(array_filter($options, function ($option) {
            return trim((string) ($option['title'] ?? '')) !== '';
        }));

        PollingOption::where('activity_id', $activity->id)->delete();

        foreach ($validOptions as $index => $option) {
            PollingOption::create([
                'activity_id' => $activity->id,
                'title' => trim((string) $option['title']),
                'description' => trim((string) ($option['description'] ?? '')),
                'image' => trim((string) ($option['image'] ?? '')),
                'sort' => (int) ($option['sort'] ?? $index + 1),
            ]);
        }
    }

    /**
     * 格式化活动数据输出
     */
    public function formatActivity(PollingActivity $activity, bool $withOptions = false): array
    {
        $data = $activity->toArray();
        $data['effective_status'] = $activity->effective_status;
        $data['effective_status_text'] = self::STATUS_MAP[$activity->effective_status] ?? '未知';
        $data['type_text'] = self::TYPE_MAP[$activity->type] ?? $activity->type;
        $data['status_text'] = self::STATUS_MAP[$activity->status] ?? '未知';
        $data['is_votable'] = $activity->isVotable();

        if ($withOptions) {
            $data['options'] = $activity->options->map(function (PollingOption $option) {
                return [
                    'id' => $option->id,
                    'title' => $option->title,
                    'description' => $option->description,
                    'image' => $option->image,
                    'sort' => $option->sort,
                ];
            })->toArray();
        }

        return $data;
    }
}
