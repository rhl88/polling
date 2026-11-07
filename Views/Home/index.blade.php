@extends('cmspro.polling::layouts.home')

@section('title', '投票 - 投票列表')

@section('content')
<div class="page-head">
    <h1 class="page-title">进行中的投票</h1>
    <p class="page-sub">选择感兴趣的投票活动，投出你的一票</p>
</div>

@if($activities->isEmpty())
    <div class="empty-box">
        <div class="empty-icon"><i class="fa fa-inbox" aria-hidden="true"></i></div>
        <p>暂时没有进行中的投票活动</p>
    </div>
@else
    <div class="activity-grid">
        @foreach($activities as $activity)
            <a class="activity-item" href="/cmspro/polling/{{ $activity->id }}">
                <div class="item-head">
                    <span class="type-tag type-{{ $activity->type }}">{{ $typeMap[$activity->type] ?? $activity->type }}</span>
                    @if($activity->effective_status === \App\Apps\CmsproPolling\Models\PollingActivity::STATUS_ENDED)
                        <span class="status-tag ended">已截止</span>
                    @endif
                </div>
                <div class="item-title">{{ $activity->title }}</div>
                @if($activity->description)
                    <div class="item-desc">{{ \Illuminate\Support\Str::limit(strip_tags($activity->description), 60) }}</div>
                @endif
                <div class="item-meta">
                    <span>{{ $activity->records_count }} 票</span>
                    @if($activity->end_time)
                        <span>截止至 {{ $activity->end_time }}</span>
                    @else
                        <span>长期有效</span>
                    @endif
                </div>
            </a>
        @endforeach
    </div>

    <div class="pagination">
        @if($activities->onFirstPage())
            <span class="page-btn disabled">上一页</span>
        @else
            <a class="page-btn" href="{{ $activities->previousPageUrl() }}">上一页</a>
        @endif
        <span class="page-info">第 {{ $activities->currentPage() }} 页 / 共 {{ $activities->lastPage() }} 页</span>
        @if($activities->hasMorePages())
            <a class="page-btn" href="{{ $activities->nextPageUrl() }}">下一页</a>
        @else
            <span class="page-btn disabled">下一页</span>
        @endif
    </div>
@endif
@endsection

@push('styles')
<style>
    .page-head { margin-bottom: 22px; }
    .page-title { font-size: 24px; font-weight: 700; }
    .page-sub { color: var(--poll-text-light); font-size: 14px; margin-top: 6px; }
    /* 响应式网格（UI 规范 10.3，移动端优先）：手机基准双列，平板/桌面逐级升列 */
    .activity-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    /* 超小屏（< 360px）：压缩间距与字号 */
    @media (max-width: 359.98px) {
        .activity-grid { gap: 8px; }
        .activity-item { padding: 12px; }
        .item-title { font-size: 14px; }
        .item-desc { font-size: 12px; }
        .item-meta { font-size: 11px; }
    }
    /* 平板（≥ 768px）：升级 3 列，卡片间距增至 14px */
    @media (min-width: 768px) {
        .activity-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
    }
    /* 桌面（≥ 1024px）：升级 4 列（容器 640px 居中，见布局层） */
    @media (min-width: 1024px) {
        .activity-grid { grid-template-columns: repeat(4, 1fr); }
    }
    .activity-item {
        background: var(--poll-card);
        border: 1px solid var(--poll-border);
        border-radius: 12px;
        padding: 18px;
        color: var(--poll-text);
        transition: transform .2s, box-shadow .2s, border-color .2s;
        display: block;
    }
    .activity-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 18px rgba(79, 110, 247, .12);
        border-color: var(--poll-primary);
        color: var(--poll-text);
    }
    .item-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .type-tag { font-size: 12px; padding: 2px 10px; border-radius: 10px; }
    .type-single { background: #e8ecff; color: var(--poll-primary); }
    .type-multiple { background: #e0f7f0; color: #0ca678; }
    .type-ranking { background: #f1e8ff; color: #9c36b5; }
    .status-tag.ended { font-size: 12px; color: #e8590c; }
    .item-title { font-size: 16px; font-weight: 600; line-height: 1.5; margin-bottom: 6px; }
    .item-desc { font-size: 13px; color: var(--poll-text-light); line-height: 1.5; min-height: 40px; }
    .item-meta { display: flex; justify-content: space-between; font-size: 12px; color: var(--poll-text-light); margin-top: 10px; }
    .empty-box {
        background: var(--poll-card);
        border-radius: 12px;
        text-align: center;
        padding: 60px 0;
        color: var(--poll-text-light);
    }
    .empty-icon {
        font-size: 48px;
        color: #999;
        margin-bottom: 10px;
    }
    .pagination { display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 26px; }
    .page-btn {
        padding: 7px 18px;
        border-radius: 18px;
        border: 1px solid var(--poll-border);
        background: var(--poll-card);
        color: var(--poll-text);
        font-size: 13px;
        transition: border-color .2s, color .2s;
    }
    .page-btn:hover:not(.disabled) { border-color: var(--poll-primary); color: var(--poll-primary); }
    .page-btn.disabled { opacity: .45; pointer-events: none; }
    .page-info { font-size: 13px; color: var(--poll-text-light); }
</style>
@endpush
