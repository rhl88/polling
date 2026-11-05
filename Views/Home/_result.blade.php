{{-- 投票结果片段（供详情页引用） --}}
@if(! empty($stats))
    @foreach($stats['options'] as $option)
        <div class="result-row">
            <div class="row-head">
                <span class="r-title">{{ $option['title'] }}</span>
                <span class="r-num">
                    @if(($stats['type'] ?? '') === 'ranking')
                        综合得分 <b>{{ $option['score'] ?? 0 }}</b>
                        @if(! empty($option['rank_distribution']))
                            （{{ collect($option['rank_distribution'])->map(function ($count, $rank) { return "第{$rank}名 {$count}次"; })->implode('、') }}）
                        @endif
                    @else
                        <b>{{ $option['count'] ?? 0 }}</b> 票 · {{ $option['percent'] ?? 0 }}%
                    @endif
                </span>
            </div>
            <div class="bar-track">
                @php
                    $width = ($stats['type'] ?? '') === 'ranking'
                        ? ($stats['total_records'] > 0
                            ? round((($option['score'] ?? 0) / max(1, collect($stats['options'])->max('score'))) * 100, 1)
                            : 0)
                        : ($option['percent'] ?? 0);
                    $ratio = min(1, max(0, $width / 100));
                @endphp
                <div class="bar-fill {{ ($stats['type'] ?? '') === 'ranking' ? 'rank' : '' }}" style="transform: scaleX({{ $ratio }})"></div>
            </div>
        </div>
    @endforeach
@endif
