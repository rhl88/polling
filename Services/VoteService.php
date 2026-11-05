<?php

namespace App\Apps\CmsproPolling\Services;

use App\Apps\CmsproPolling\Exceptions\VoteException;
use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Models\PollingOption;
use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\HookManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoteService
{
    public function __construct(protected HookManager $hooks)
    {
    }

    /**
     * 提交投票
     *
     * @param PollingActivity $activity 目标活动
     * @param array $optionIds 本次选择的选项ID列表（ranking 时按名次升序提交）
     * @param array $rankMap ranking 时可显式传选项ID=>名次映射，为空时按 optionIds 顺序生成
     * @param User|null $user 当前登录用户（游客为 null）
     * @param string $ip 客户端IP
     */
    public function submitVote(PollingActivity $activity, array $optionIds, array $rankMap = [], ?User $user = null, string $ip = ''): array
    {
        // 1. 活动状态与时间校验
        if (!$activity->isVotable()) {
            return ApiResponse::error(40001, $activity->effective_status === PollingActivity::STATUS_DRAFT
                ? '该投票活动未发布'
                : '该投票活动已结束');
        }

        // 2. 登录校验
        if ((int) $activity->require_login === 1 && $user === null) {
            return ApiResponse::error(40101, '该投票活动需要登录后参与');
        }

        $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
        if (empty($optionIds)) {
            return ApiResponse::error(40001, '请选择投票选项');
        }

        // 3. 选项归属校验
        $validOptionIds = PollingOption::where('activity_id', $activity->id)
            ->whereIn('id', $optionIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (count($validOptionIds) !== count($optionIds)) {
            return ApiResponse::error(40001, '包含无效的投票选项');
        }

        // 4. 投票类型规则校验
        $error = $this->validateByType($activity, $optionIds);
        if ($error !== null) {
            return ApiResponse::error(40001, $error);
        }

        // 5. 名次映射规范化（ranking）
        $ranks = [];
        if ($activity->isRanking()) {
            $ranks = $this->normalizeRanks($optionIds, $rankMap);
        }

        $userId = $user ? (int) $user->id : 0;

        try {
            $records = DB::transaction(function () use ($activity, $optionIds, $ranks, $userId, $ip) {
                // 活动行锁，防止并发批次号冲突
                $lockedActivity = PollingActivity::where('id', $activity->id)->lockForUpdate()->first();

                // 6. 限投次数校验（登录用户按用户维度，游客按IP维度）
                $votedCount = $this->getVotedBatchCount($activity->id, $userId, $ip);
                if ($votedCount >= (int) $activity->votes_per_user) {
                    throw new VoteException('您已达到该活动的投票次数上限');
                }

                // 7. IP 防刷校验（同IP提交批次上限）
                if ((int) $activity->ip_limit > 0) {
                    $ipBatches = $this->getIpBatchCount($activity->id, $ip);
                    if ($ipBatches >= (int) $activity->ip_limit) {
                        throw new VoteException('该IP的投票次数已达上限');
                    }
                }

                // 8. 生成批次号并写入记录
                $maxVoteNo = (int) PollingRecord::where('activity_id', $activity->id)
                    ->where('user_id', $userId)
                    ->when($userId === 0, function ($query) use ($ip) {
                        $query->where('ip', $ip);
                    })
                    ->max('vote_no');

                $voteNo = $maxVoteNo + 1;
                $records = [];
                foreach ($optionIds as $index => $optionId) {
                    $records[] = PollingRecord::create([
                        'activity_id' => $activity->id,
                        'option_id' => $optionId,
                        'user_id' => $userId,
                        'vote_no' => $voteNo,
                        'rank' => $activity->isRanking() ? ($ranks[$optionId] ?? $index + 1) : 0,
                        'ip' => $ip,
                    ]);
                }

                return $records;
            });
        } catch (VoteException $e) {
            return ApiResponse::error(40001, $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('投票提交失败', ['activity_id' => $activity->id, 'error' => $e->getMessage()]);
            return ApiResponse::error(50001, '投票提交失败，请稍后重试');
        }

        // 9. 触发自定义钩子：投票提交成功
        $this->hooks->doAction('polling.vote.submitted', $activity, $records);

        return ApiResponse::success([
            'vote_no' => $records[0]->vote_no,
            'option_count' => count($records),
        ], '投票成功');
    }

    /**
     * 活动统计结果
     *
     * 返回结构：
     * - type：投票类型
     * - total_records：总记录数
     * - total_votes：参与人次（去重批次）
     * - options：各选项统计（票数/得分/占比/名次分布）
     */
    public function getStats(PollingActivity $activity): array
    {
        $options = $activity->options->values();
        $optionCount = $options->count();

        $records = PollingRecord::where('activity_id', $activity->id)->get();

        // 参与人次：按（IP+用户+批次）去重
        $totalVotes = $records->groupBy(fn ($record) => $record->ip . '|' . $record->user_id . '|' . $record->vote_no)->count();

        $optionStats = [];
        foreach ($options as $option) {
            $optionRecords = $records->where('option_id', $option->id);
            $count = $optionRecords->count();

            $stat = [
                'id' => $option->id,
                'title' => $option->title,
                'description' => $option->description,
                'image' => $option->image,
                'sort' => $option->sort,
                'count' => $count,
                'percent' => 0.0,
            ];

            if ($activity->isRanking()) {
                // Borda 计分：选项总数 - 名次；并统计各名次分布
                $score = 0;
                $rankDistribution = [];
                foreach ($optionRecords as $record) {
                    $score += max(0, $optionCount - (int) $record->rank);
                    $rankDistribution[(int) $record->rank] = ($rankDistribution[(int) $record->rank] ?? 0) + 1;
                }
                ksort($rankDistribution);
                $stat['score'] = $score;
                $stat['rank_distribution'] = $rankDistribution;
            } else {
                $totalOptionRecords = $records->count();
                $stat['percent'] = $totalOptionRecords > 0 ? round($count * 100 / $totalOptionRecords, 2) : 0.0;
            }

            $optionStats[] = $stat;
        }

        // 排序投票按得分降序排列展示
        if ($activity->isRanking()) {
            usort($optionStats, fn ($a, $b) => $b['score'] <=> $a['score']);
        } else {
            usort($optionStats, fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        $stats = [
            'type' => $activity->type,
            'total_records' => $records->count(),
            'total_votes' => $totalVotes,
            'options' => $optionStats,
        ];

        // 触发自定义 Filter 钩子：允许其他应用加工统计数据
        return (array) $this->hooks->applyFilter('polling.activity.stats', $stats, $activity);
    }

    /**
     * 结果是否对当前访问者可见
     */
    public function canViewResult(PollingActivity $activity, ?User $user, string $ip): bool
    {
        return match ($activity->result_visibility) {
            PollingActivity::VISIBILITY_NEVER => false,
            PollingActivity::VISIBILITY_ALWAYS => true,
            PollingActivity::VISIBILITY_AFTER_VOTE => $this->hasVoted($activity, $user, $ip),
            PollingActivity::VISIBILITY_END => $activity->effective_status === PollingActivity::STATUS_ENDED,
            default => false,
        };
    }

    /**
     * 当前访问者是否已参与过该活动
     */
    public function hasVoted(PollingActivity $activity, ?User $user, string $ip): bool
    {
        return $this->getVotedBatchCount($activity->id, $user ? (int) $user->id : 0, $ip) > 0;
    }

    /**
     * 导出统计报告 CSV
     */
    public function exportStatsCsv(PollingActivity $activity): string
    {
        $stats = $this->getStats($activity);

        $lines = [];
        $lines[] = '投票统计报告';
        $lines[] = '活动标题,' . $this->csvCell($activity->title);
        $lines[] = '投票类型,' . (ActivityService::TYPE_MAP[$activity->type] ?? $activity->type);
        $lines[] = '开始时间,' . ($activity->start_time ?: '-');
        $lines[] = '结束时间,' . ($activity->end_time ?: '-');
        $lines[] = '参与人次,' . $stats['total_votes'];
        $lines[] = '总记录数,' . $stats['total_records'];
        $lines[] = '';

        if ($activity->isRanking()) {
            $lines[] = '选项,综合得分,被排名次数明细';
            foreach ($stats['options'] as $option) {
                $distribution = collect($option['rank_distribution'] ?? [])
                    ->map(fn ($count, $rank) => "第{$rank}名:{$count}次")
                    ->implode(' / ');
                $lines[] = $this->csvCell($option['title']) . ',' . $option['score'] . ',' . $this->csvCell($distribution);
            }
        } else {
            $lines[] = '选项,票数,占比(%)';
            foreach ($stats['options'] as $option) {
                $lines[] = $this->csvCell($option['title']) . ',' . $option['count'] . ',' . $option['percent'];
            }
        }

        $lines[] = '';
        $lines[] = '投票记录明细';
        $lines[] = '记录ID,选项,用户名,第几次投票,名次,IP,投票时间';

        PollingRecord::where('activity_id', $activity->id)
            ->with(['option', 'user'])
            ->orderBy('id')
            ->chunk(500, function ($records) use (&$lines, $activity) {
                foreach ($records as $record) {
                    $username = $record->user_id > 0 ? ($record->user?->username ?? '-') : ($activity->anonymous ? '匿名' : '游客');
                    if ($activity->anonymous && $record->user_id > 0) {
                        $username = '匿名';
                    }
                    $lines[] = implode(',', [
                        $record->id,
                        $this->csvCell($record->option?->title ?? '-'),
                        $this->csvCell($username),
                        $record->vote_no,
                        $record->rank > 0 ? $record->rank : '-',
                        $this->csvCell($record->ip ?? '-'),
                        (string) $record->create_time,
                    ]);
                }
            });

        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }

    /**
     * 当前访问者在该活动已使用的投票批次数
     */
    protected function getVotedBatchCount(int $activityId, int $userId, string $ip): int
    {
        if ($userId > 0) {
            // 已登录用户：历史游客投票的 IP 可能相同，仅按用户维度统计
            return PollingRecord::where('activity_id', $activityId)
                ->where('user_id', $userId)
                ->groupBy('vote_no')
                ->get()
                ->count();
        }

        // 游客按 IP 维度统计
        return PollingRecord::where('activity_id', $activityId)
            ->where('user_id', 0)
            ->where('ip', $ip)
            ->groupBy('vote_no')
            ->get()
            ->count();
    }

    /**
     * 同一 IP 在该活动的投票批次数（含登录用户）
     */
    protected function getIpBatchCount(int $activityId, string $ip): int
    {
        return PollingRecord::where('activity_id', $activityId)
            ->where('ip', $ip)
            ->groupBy(['user_id', 'vote_no'])
            ->get()
            ->count();
    }

    /**
     * 投票类型规则校验，返回错误信息（null 表示通过）
     */
    protected function validateByType(PollingActivity $activity, array $optionIds): ?string
    {
        $count = count($optionIds);

        return match ($activity->type) {
            PollingActivity::TYPE_SINGLE => $count === 1 ? null : '单选投票只能选择1个选项',
            PollingActivity::TYPE_MULTIPLE => ($count >= 1 && $count <= (int) $activity->max_choices)
                ? null
                : '多选投票需选择1到' . $activity->max_choices . '个选项',
            PollingActivity::TYPE_RANKING => $count >= 2 ? null : '排序投票至少需要对2个选项排序',
            default => '无效的投票类型',
        };
    }

    /**
     * 规范化排序投票的名次映射（1 开始连续）
     */
    protected function normalizeRanks(array $optionIds, array $rankMap): array
    {
        $ranks = [];
        if (!empty($rankMap)) {
            foreach ($rankMap as $optionId => $rank) {
                $optionId = (int) $optionId;
                if (in_array($optionId, $optionIds, true)) {
                    $ranks[$optionId] = max(1, (int) $rank);
                }
            }
        }

        // 未提供映射时按提交顺序生成名次
        foreach ($optionIds as $index => $optionId) {
            if (!isset($ranks[$optionId])) {
                $ranks[$optionId] = $index + 1;
            }
        }

        return $ranks;
    }

    /**
     * CSV 单元格转义
     */
    protected function csvCell(string $value): string
    {
        $value = str_replace('"', '""', $value);
        return str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")
            ? '"' . $value . '"'
            : $value;
    }
}

