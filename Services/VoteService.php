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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $isRanking = $activity->isRanking();

        // 总记录数：库端 COUNT 聚合，不整表拉取记录
        $totalRecords = PollingRecord::where('activity_id', $activity->id)->count();

        // 参与人次：按（IP+用户+批次）元组去重后计数。
        // 不用 count(distinct ip, user_id, vote_no)：SQLite 的 DISTINCT 聚合只支持单参数，
        // 以分组子查询计数同时兼容 SQLite 测试库与 MySQL only_full_group_by 模式
        $totalVotes = DB::query()
            ->fromSub(
                PollingRecord::query()
                    ->where('activity_id', $activity->id)
                    ->select(['ip', 'user_id', 'vote_no'])
                    ->groupBy(['ip', 'user_id', 'vote_no']),
                'batches'
            )
            ->count();

        // 选项维度聚合：按（选项+名次）分组在库端计数，PHP 端只做小规模汇总
        $aggRows = PollingRecord::query()
            ->where('activity_id', $activity->id)
            ->select(['option_id', 'rank', DB::raw('COUNT(*) as cnt')])
            ->groupBy(['option_id', 'rank'])
            ->get();

        $aggByOption = [];
        foreach ($aggRows as $row) {
            $optionId = (int) $row->option_id;
            $aggByOption[$optionId] ??= ['count' => 0, 'ranks' => []];
            $aggByOption[$optionId]['count'] += (int) $row->cnt;
            $aggByOption[$optionId]['ranks'][(int) $row->rank] = (int) $row->cnt;
        }

        $optionStats = [];
        foreach ($options as $option) {
            $agg = $aggByOption[(int) $option->id] ?? ['count' => 0, 'ranks' => []];

            $stat = [
                'id' => $option->id,
                'title' => $option->title,
                'description' => $option->description,
                'image' => $option->image,
                'sort' => $option->sort,
                'count' => $agg['count'],
                'percent' => 0.0,
            ];

            if ($isRanking) {
                // Borda 计分：选项总数 - 名次（同库端合并后的名次计数 × 得分累加）
                [$score, $rankDistribution] = $this->computeRankingScore($agg['ranks'], $optionCount);
                $stat['score'] = $score;
                $stat['rank_distribution'] = $rankDistribution;
            } else {
                $stat['percent'] = $totalRecords > 0 ? round($agg['count'] * 100 / $totalRecords, 2) : 0.0;
            }

            $optionStats[] = $stat;
        }

        // 排序投票按得分降序排列展示，其他类型按票数降序
        if ($isRanking) {
            usort($optionStats, fn ($a, $b) => $b['score'] <=> $a['score']);
        } else {
            usort($optionStats, fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        $stats = [
            'type' => $activity->type,
            'total_records' => $totalRecords,
            'total_votes' => $totalVotes,
            'options' => $optionStats,
        ];

        // 触发自定义 Filter 钩子：允许其他应用加工统计数据
        return (array) $this->hooks->applyFilter('polling.activity.stats', $stats, $activity);
    }

    /**
     * Borda 计分与名次分布（基于库端按名次合并后的计数）
     *
     * @param array<int, int> $rankCounts 名次 => 该名次记录数
     * @return array{0: int, 1: array<int, int>} [综合得分, 名次分布]
     */
    protected function computeRankingScore(array $rankCounts, int $optionCount): array
    {
        $score = 0;
        $rankDistribution = [];
        foreach ($rankCounts as $rank => $cnt) {
            $score += $cnt * max(0, $optionCount - $rank);
            $rankDistribution[$rank] = $cnt;
        }
        ksort($rankDistribution);

        return [$score, $rankDistribution];
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
     * 导出统计报告 CSV（流式响应）
     *
     * 统计摘要 + 记录明细经 php://output 流式写出，
     * 内存占用与活动规模解耦（不整份累积 CSV 文本）；
     * 前端 window.open 直接触发浏览器按附件保存
     */
    public function exportStatsCsv(PollingActivity $activity): StreamedResponse
    {
        $stats = $this->getStats($activity);
        $filename = '投票统计_' . $activity->id . '_' . date('Ymd_His') . '.csv';

        $render = function () use ($activity, $stats) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM 头：保证 Excel 以 UTF-8 打开中文不乱码
            fwrite($out, "\xEF\xBB\xBF");

            // RFC 4180：显式空 escape（规避反斜杠转义语义），行尾统一 CRLF
            $writeRow = fn (array $fields) => fputcsv($out, $fields, ',', '"', '', "\r\n");

            $this->writeStatsSummary($writeRow, $activity, $stats);
            $this->writeRecordRows($writeRow, $activity);

            fclose($out);
        };

        return response()->streamDownload($render, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 写出统计摘要段（报告头 + 选项统计表）
     *
     * @param \Closure $writeRow 单行写出器
     */
    protected function writeStatsSummary(\Closure $writeRow, PollingActivity $activity, array $stats): void
    {
        $writeRow(['投票统计报告']);
        $writeRow(['活动标题', (string) $activity->title]);
        $writeRow(['投票类型', ActivityService::TYPE_MAP[$activity->type] ?? $activity->type]);
        $writeRow(['开始时间', (string) ($activity->start_time ?: '-')]);
        $writeRow(['结束时间', (string) ($activity->end_time ?: '-')]);
        $writeRow(['参与人次', $stats['total_votes']]);
        $writeRow(['总记录数', $stats['total_records']]);
        $writeRow([]);

        if ($activity->isRanking()) {
            $writeRow(['选项', '综合得分', '被排名次数明细']);
            foreach ($stats['options'] as $option) {
                $distribution = collect($option['rank_distribution'] ?? [])
                    ->map(fn ($count, $rank) => "第{$rank}名:{$count}次")
                    ->implode(' / ');
                $writeRow([$option['title'], $option['score'], $distribution]);
            }
        } else {
            $writeRow(['选项', '票数', '占比(%)']);
            foreach ($stats['options'] as $option) {
                $writeRow([$option['title'], $option['count'], $option['percent']]);
            }
        }
    }

    /**
     * 写出投票记录明细段（chunkById 游标分块流式拉取）
     *
     * @param \Closure $writeRow 单行写出器
     */
    protected function writeRecordRows(\Closure $writeRow, PollingActivity $activity): void
    {
        $writeRow([]);
        $writeRow(['投票记录明细']);
        $writeRow(['记录ID', '选项', '用户名', '第几次投票', '名次', 'IP', '投票时间']);

        // chunkById 以主键游标分页：相比 offset 分页的 chunk，
        // 导出期间并发写入的新记录不会因页偏移漂移而漏行或重行
        PollingRecord::query()
            ->where('activity_id', $activity->id)
            ->with(['option', 'user'])
            ->orderBy('id')
            ->chunkById(500, function ($records) use ($writeRow, $activity) {
                foreach ($records as $record) {
                    $username = $record->user_id > 0 ? ($record->user?->username ?? '-') : ($activity->anonymous ? '匿名' : '游客');
                    if ($activity->anonymous && $record->user_id > 0) {
                        $username = '匿名';
                    }
                    $writeRow([
                        $record->id,
                        $record->option?->title ?? '-',
                        $username,
                        $record->vote_no,
                        $record->rank > 0 ? $record->rank : '-',
                        $record->ip ?? '-',
                        (string) $record->create_time,
                    ]);
                }
            });
    }

    /**
     * 当前访问者在该活动已使用的投票批次数
     */
    protected function getVotedBatchCount(int $activityId, int $userId, string $ip): int
    {
        if ($userId > 0) {
            // 已登录用户：历史游客投票的 IP 可能相同，仅按用户维度统计
            // count(distinct) 在库内完成去重，规避 select * + group by 触发 MySQL only_full_group_by 报 1055
            return PollingRecord::where('activity_id', $activityId)
                ->where('user_id', $userId)
                ->distinct()
                ->count('vote_no');
        }

        // 游客按 IP 维度统计
        return PollingRecord::where('activity_id', $activityId)
            ->where('user_id', 0)
            ->where('ip', $ip)
            ->distinct()
            ->count('vote_no');
    }

    /**
     * 同一 IP 在该活动的投票批次数（含登录用户）
     */
    protected function getIpBatchCount(int $activityId, string $ip): int
    {
        // 按 (user_id, vote_no) 元组去重；SELECT 限定为分组列以满足 MySQL only_full_group_by 模式。
        // 不用 count(distinct user_id, vote_no)：MySQL 支持多参数形式，但 SQLite 会报
        // "DISTINCT aggregates must have exactly one argument"，为兼容测试库放弃该写法
        return PollingRecord::where('activity_id', $activityId)
            ->where('ip', $ip)
            ->select(['user_id', 'vote_no'])
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
}

