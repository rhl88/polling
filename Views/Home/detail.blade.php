@extends('cmspro.polling::layouts.home')

@section('title', $activity->title . ' - 投票')

@section('content')
<div class="detail-card">
    <div class="detail-head">
        <span class="type-tag type-{{ $activity->type }}">{{ $typeMap[$activity->type] ?? $activity->type }}</span>
        @if($activity->effective_status === \App\Apps\CmsproPolling\Models\PollingActivity::STATUS_DRAFT)
            <span class="badge badge-gray">未发布</span>
        @elseif($activity->effective_status === \App\Apps\CmsproPolling\Models\PollingActivity::STATUS_ACTIVE)
            <span class="badge badge-green">进行中</span>
        @else
            <span class="badge badge-orange">已截止</span>
        @endif
    </div>

    <h1 class="detail-title">{{ $activity->title }}</h1>

    @if($activity->description)
        <div class="detail-desc">{!! nl2br(e($activity->description)) !!}</div>
    @endif

    <div class="detail-meta">
        <span>开始：{{ $activity->start_time ?: '不限' }}</span>
        <span>截止：{{ $activity->end_time ?: '不限' }}</span>
        <span>每人限投 {{ $activity->votes_per_user }} 次</span>
        @if($activity->require_login)
            <span class="meta-warn">需登录后投票</span>
        @endif
    </div>
</div>

<div class="vote-card">
    <div class="vote-card-title">
        投票区域
        @if($activity->type === 'multiple')
            <span class="vote-tip">最多可选 {{ $activity->max_choices }} 项</span>
        @elseif($activity->type === 'ranking')
            <span class="vote-tip">按偏好依次点击选项，1 为最偏好</span>
        @endif
    </div>

    <div id="option-area">
        @foreach($activity->options as $option)
            <div class="option-item" data-id="{{ $option->id }}" role="button" tabindex="0" aria-pressed="false">
                @if($activity->type === 'ranking')
                    <span class="rank-badge" data-badge></span>
                @endif
                <div class="option-body">
                    <div class="option-title">{{ $option->title }}</div>
                    @if($option->description)
                        <div class="option-desc">{{ $option->description }}</div>
                    @endif
                </div>
                <span class="check-mark" data-check aria-hidden="true"></span>
            </div>
        @endforeach
    </div>

    <div class="vote-actions">
        <button type="button" class="btn-primary" id="btn-vote" disabled>投出你的一票</button>
        <div class="vote-hint" id="vote-hint"></div>
    </div>
</div>

<div class="result-card" id="result-card" style="display: {{ $canViewResult ? 'block' : 'none' }};">
    <div class="vote-card-title">
        投票结果
        @if($stats)
            <span class="vote-tip">已有 {{ $stats['total_votes'] }} 人参与，共 {{ $stats['total_records'] }} 票</span>
        @endif
    </div>
    <div id="result-area">
        @if($stats)
            @include('cmspro.polling::Home._result', ['stats' => $stats])
        @endif
    </div>
</div>

@if(! $canViewResult)
<div class="result-hidden-tip">
    @if($activity->result_visibility === 'after_vote')
        投票后可见实时结果
    @elseif($activity->result_visibility === 'end')
        投票截止后公开结果
    @endif
</div>
@endif
@endsection

@push('styles')
<style>
    .detail-card, .vote-card, .result-card {
        background: var(--poll-card);
        border: 1px solid var(--poll-border);
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 16px;
    }
    .detail-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .detail-title { font-size: 22px; font-weight: 700; line-height: 1.4; margin-bottom: 10px; }
    .detail-desc { font-size: 14px; color: #555; line-height: 1.8; margin-bottom: 12px; }
    .detail-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: var(--poll-text-light); }
    .meta-warn { color: #e8590c; }
    .type-tag { font-size: 12px; padding: 2px 10px; border-radius: 10px; }
    .type-single { background: #e8ecff; color: var(--poll-primary); }
    .type-multiple { background: #e0f7f0; color: #0ca678; }
    .type-ranking { background: #f1e8ff; color: #9c36b5; }
    .badge { font-size: 12px; padding: 2px 10px; border-radius: 10px; }
    .badge-green { background: #e0f7f0; color: #0ca678; }
    .badge-orange { background: #fff0e6; color: #e8590c; }
    .badge-gray { background: #f1f2f5; color: #888; }
    .vote-card-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .vote-tip { font-size: 12px; font-weight: 400; color: var(--poll-text-light); }
    .option-item {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 2px solid var(--poll-border);
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: border-color .15s, background-color .15s;
        position: relative;
        background: #fff;
    }
    .option-item:focus-visible {
        outline: 2px solid var(--poll-primary);
        outline-offset: 2px;
    }
    .option-item:hover { border-color: #b9c4fa; }
    .option-item.selected {
        border-color: var(--poll-primary);
        background: #f5f7ff;
    }
    .option-item.disabled {
        cursor: not-allowed;
        opacity: .6;
    }
    .option-body { flex: 1; }
    .option-title { font-size: 15px; font-weight: 500; line-height: 1.5; }
    .option-desc { font-size: 12px; color: var(--poll-text-light); margin-top: 4px; line-height: 1.5; }
    .rank-badge {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #f1f2f5;
        color: #999;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .rank-badge.active { background: #9c36b5; color: #fff; }
    .check-mark {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 2px solid #d5d8e0;
        display: inline-flex;
        flex-shrink: 0;
        position: relative;
        transition: background-color .15s, border-color .15s;
    }
    /* 选中打勾：纯 CSS 绘制，避免 Unicode 符号跨平台渲染不一致 */
    .option-item.selected .check-mark {
        background: var(--poll-primary);
        border-color: var(--poll-primary);
    }
    .option-item.selected .check-mark::after {
        content: '';
        position: absolute;
        left: 7px;
        top: 3px;
        width: 5px;
        height: 10px;
        border: solid #fff;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
    }
    .vote-actions { margin-top: 18px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .btn-primary {
        background: var(--poll-primary);
        color: #fff;
        border: none;
        border-radius: 24px;
        padding: 11px 46px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color .2s, transform .2s;
    }
    .btn-primary:hover:not(:disabled) { background: var(--poll-primary-dark); transform: translateY(-1px); }
    .btn-primary:disabled { opacity: .45; cursor: not-allowed; }
    .vote-hint { font-size: 13px; color: var(--poll-text-light); }
    .result-hidden-tip { text-align: center; color: var(--poll-text-light); font-size: 13px; padding: 6px 0 20px; }
    /* 轻量 Toast（替代 alert，见交互规范） */
    .poll-toast {
        position: fixed;
        top: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(-10px);
        background: #333;
        color: #fff;
        padding: 10px 22px;
        border-radius: 20px;
        font-size: 14px;
        opacity: 0;
        pointer-events: none;
        z-index: 999;
        transition: opacity .25s, transform .25s;
    }
    .poll-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .poll-toast.toast-error { background: #e8590c; }
    /* 结果区条形图 */
    .result-row { margin-bottom: 16px; }
    .result-row .row-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        font-size: 14px;
        margin-bottom: 5px;
    }
    .result-row .row-head .r-title { font-weight: 500; }
    .result-row .row-head .r-num { color: var(--poll-text-light); font-size: 12px; }
    .result-row .row-head .r-num b { color: var(--poll-primary); font-size: 14px; }
    .bar-track { background: #eef0f5; border-radius: 9px; height: 16px; overflow: hidden; }
    .bar-fill {
        height: 100%;
        width: 100%;
        border-radius: 9px;
        background: linear-gradient(90deg, var(--poll-primary), #748ffc);
        transform-origin: left center;
        animation: bar-grow .3s ease both;
    }
    .bar-fill.rank { background: linear-gradient(90deg, #9c36b5, #d0bfff); }
    @keyframes bar-grow {
        from { transform: scaleX(0); }
    }
</style>
@endpush

@push('scripts')
<script>
    (function() {
        var activityId = '{{ $activity->id }}';
        var activityType = '{{ $activity->type }}';
        var maxChoices = parseInt('{{ $activity->max_choices }}', 10) || 1;
        var votesPerUser = parseInt('{{ $activity->votes_per_user }}', 10) || 1;
        var requireLogin = '{{ $activity->require_login }}' === '1';
        var isVotable = '{{ $activity->isVotable() ? '1' : '0' }}' === '1';
        var hasVoted = '{{ $hasVoted ? '1' : '0' }}' === '1';
        var isGuest = '{{ $user ? '0' : '1' }}' === '1';

        // 按选择顺序维护选项ID
        var selected = [];

        var optionArea = document.getElementById('option-area');
        var btnVote = document.getElementById('btn-vote');
        var voteHint = document.getElementById('vote-hint');
        var optionItems = optionArea.querySelectorAll('.option-item');

        // 轻量 Toast（替代 alert，避免阻塞式弹窗打断操作流）
        function showToast(message, type) {
            var existing = document.querySelector('.poll-toast');
            if (existing) {
                existing.remove();
            }
            var toast = document.createElement('div');
            toast.className = 'poll-toast' + (type === 'error' ? ' toast-error' : '');
            toast.textContent = message;
            document.body.appendChild(toast);
            requestAnimationFrame(function() {
                toast.classList.add('show');
            });
            setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.remove(); }, 300);
            }, 2400);
        }

        function optionIsVotingBlocked() {
            return !isVotable;
        }

        function refreshUI() {
            optionItems.forEach(function(item) {
                var id = item.getAttribute('data-id');
                var index = selected.indexOf(id);

                item.classList.toggle('selected', index !== -1);
                item.setAttribute('aria-pressed', index !== -1 ? 'true' : 'false');

                // 排序徽章
                var badge = item.querySelector('[data-badge]');
                if (badge) {
                    if (index !== -1) {
                        badge.textContent = index + 1;
                        badge.classList.add('active');
                    } else {
                        badge.textContent = '';
                        badge.classList.remove('active');
                    }
                }
            });

            var minSelect = activityType === 'ranking' ? 2 : 1;
            btnVote.disabled = selected.length < minSelect || optionIsVotingBlocked();

            if (optionIsVotingBlocked()) {
                voteHint.textContent = isVotable ? '' : '当前不在投票时间内';
            } else if (requireLogin && isGuest) {
                voteHint.innerHTML = '该投票需要 <a href="/user/login">登录</a> 后参与';
            } else if (activityType === 'multiple') {
                voteHint.textContent = '已选 ' + selected.length + ' / ' + maxChoices + ' 项';
            } else if (activityType === 'ranking') {
                voteHint.textContent = selected.length >= 2 ? '已按顺序选择 ' + selected.length + ' 项' : '请至少为 2 个选项排序';
            } else {
                voteHint.textContent = selected.length > 0 ? '已选择 1 项' : '';
            }
        }

        function toggleOption(item) {
            if (optionIsVotingBlocked()) {
                return;
            }
            if (requireLogin && isGuest) {
                voteHint.innerHTML = '该投票需要 <a href="/user/login">登录</a> 后参与';
                return;
            }

            var id = item.getAttribute('data-id');
            var index = selected.indexOf(id);

            if (index !== -1) {
                // 取消选择
                selected.splice(index, 1);
            } else if (activityType === 'single') {
                // 单选：radio 语义——点击新选项直接替换已选项，保证至多一项选中
                selected = [id];
            } else {
                // 多选超限拦截
                if (activityType === 'multiple' && selected.length >= maxChoices) {
                    voteHint.textContent = '最多只能选择 ' + maxChoices + ' 项';
                    return;
                }
                selected.push(id);
            }
            refreshUI();
        }

        optionItems.forEach(function(item) {
            item.addEventListener('click', function() {
                toggleOption(item);
            });

            // 键盘可达性：Enter / 空格 触发选择（等价鼠标点击）
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleOption(item);
                }
            });
        });

        refreshUI();

        // ---------- 提交投票 ----------
        var voting = false;

        btnVote.addEventListener('click', function() {
            if (btnVote.disabled || voting) {
                return;
            }

            var payload = {
                option_ids: selected.map(function(id) { return parseInt(id, 10); }),
                _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            };

            voting = true;
            btnVote.disabled = true;
            btnVote.textContent = '提交中…';

            fetch('/cmspro/polling/api/vote/' + activityId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': payload._token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(response) { return response.json(); })
            .then(function(r) {
                if (r.code === 0) {
                    showToast(r.message || '投票成功！', 'success');
                    // 投票后刷新页面以更新结果
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(r.message || '投票失败', 'error');
                    voting = false;
                    btnVote.disabled = false;
                    btnVote.textContent = '投出你的一票';
                }
            })
            .catch(function() {
                showToast('网络异常，请稍后重试', 'error');
                voting = false;
                btnVote.disabled = false;
                btnVote.textContent = '投出你的一票';
            });
        });
    })();
</script>
@endpush
