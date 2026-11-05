<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>投票统计</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    @include('admin.partials.permission-script')
    <style>
        .stat-card {
            display: inline-block;
            width: 180px;
            padding: 15px;
            margin-right: 10px;
            margin-bottom: 10px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            text-align: center;
        }
        .stat-card .num {
            font-size: 28px;
            font-weight: 600;
            color: #1e9fff;
            line-height: 36px;
        }
        .stat-card .label {
            color: #888;
            font-size: 13px;
        }
        .bar-row {
            margin-bottom: 14px;
        }
        .bar-row .bar-title {
            font-size: 13px;
            color: #333;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        .bar-track {
            background: #f0f0f0;
            border-radius: 10px;
            height: 18px;
            overflow: hidden;
        }
        .bar-fill {
            background: linear-gradient(90deg, #1e9fff, #5fb878);
            height: 100%;
            border-radius: 10px;
            min-width: 2px;
            transition: width .5s;
        }
        .bar-fill.rank {
            background: linear-gradient(90deg, #a06bff, #ffb800);
        }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <blockquote class="layui-elem-quote">
                <b id="activity-title">{{ $activityTitle }}</b>
                <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btn-export" data-permission="cmspro.polling.stats.export" style="float:right;margin-top:-5px;">
                    <i class="layui-icon layui-icon-export"></i> 导出统计报告
                </button>
                <button type="button" class="layui-btn layui-btn-sm" id="btn-refresh" style="float:right;margin-top:-5px;margin-right:8px;">
                    <i class="layui-icon layui-icon-refresh"></i> 刷新统计
                </button>
            </blockquote>

            <div id="summary-cards" style="margin-top:10px;"></div>
            <div id="option-stats" style="margin-top:15px;"></div>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-header">投票记录</div>
        <div class="layui-card-body">
            <table id="record-table" lay-filter="record-table"></table>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['table', 'layer', 'jquery'], function() {
        var table = layui.table;
        var layer = layui.layer;
        var $ = layui.jquery;

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        var activityId = '{{ $activityId }}';

        // 隐藏无权限按钮
        $('button[data-permission]').each(function() {
            if (window.hasPermission && !window.hasPermission($(this).data('permission'))) {
                $(this).hide();
            }
        });

        // ---------- 统计渲染 ----------
        function renderStats() {
            $.get('/admin/cmspro/polling/api/activities/' + activityId + '/stats', function(r) {
                if (r.code !== 0) {
                    layer.msg(r.message || '加载统计失败', { icon: 2 });
                    return;
                }
                var stats = r.data;
                var isRanking = stats.type === 'ranking';
                var maxScore = isRanking
                    ? Math.max.apply(null, stats.options.map(function(o) { return o.score || 0; }).concat([1]))
                    : 100;

                // 汇总卡片
                $('#summary-cards').html(
                    '<div class="stat-card"><div class="num">' + stats.total_votes + '</div><div class="label">参与人次</div></div>' +
                    '<div class="stat-card"><div class="num">' + stats.total_records + '</div><div class="label">总票数' + (isRanking ? '（含名次记录）' : '') + '</div></div>' +
                    '<div class="stat-card"><div class="num">' + stats.options.length + '</div><div class="label">选项数</div></div>'
                );

                // 选项条形图
                var html = '';
                $.each(stats.options, function(i, option) {
                    var left, right, width;
                    if (isRanking) {
                        left = option.title + '（综合得分 ' + option.score + '）';
                        right = (option.rank_distribution && Object.keys(option.rank_distribution).length)
                            ? Object.keys(option.rank_distribution).map(function(rank) { return '第' + rank + '名 ' + option.rank_distribution[rank] + '次'; }).join('、')
                            : '暂无排名';
                        width = (option.score / maxScore) * 100;
                    } else {
                        left = option.title + '（' + option.count + '票）';
                        right = option.percent + '%';
                        width = option.percent;
                    }
                    html += '<div class="bar-row">' +
                        '<div class="bar-title"><span>' + left + '</span><span>' + right + '</span></div>' +
                        '<div class="bar-track"><div class="bar-fill' + (isRanking ? ' rank' : '') + '" style="width:' + Math.min(100, width) + '%"></div></div>' +
                    '</div>';
                });
                $('#option-stats').html(html);
            });
        }

        renderStats();

        $('#btn-refresh').on('click', renderStats);

        // ---------- 导出 ----------
        $('#btn-export').on('click', function() {
            if (window.hasPermission && !window.hasPermission('cmspro.polling.stats.export')) {
                layer.msg('无操作权限', { icon: 2 });
                return;
            }
            window.open('/admin/cmspro/polling/api/activities/export/' + activityId);
        });

        // ---------- 记录子表 ----------
        table.render({
            elem: '#record-table',
            url: '/admin/cmspro/polling/api/records?activity_id=' + activityId,
            page: {
                layout: ['count', 'prev', 'page', 'next', 'limit'],
                groups: 5,
                limit: 20,
                limits: [10, 20, 50, 100]
            },
            cols: [[
                { title: 'ID', field: 'id', align: 'center', width: 80, sort: true },
                { title: '选项', field: 'option_title', align: 'center', minWidth: 160 },
                { title: '用户名', field: 'username', align: 'center', width: 120 },
                { title: '昵称', field: 'nickname', align: 'center', width: 120 },
                { title: '第几次投票', field: 'vote_no', align: 'center', width: 110 },
                { title: '名次', field: 'rank', align: 'center', width: 80, templet: function(d) { return d.rank > 0 ? d.rank : '-'; } },
                { title: 'IP', field: 'ip', align: 'center', width: 140 },
                { title: '投票时间', field: 'create_time', align: 'center', width: 170 }
            ]],
            skin: false,
            defaultToolbar: [{
                title: '刷新',
                layEvent: 'refresh',
                icon: 'layui-icon-refresh'
            }, 'filter', 'print', 'exports'],
            parseData: function(res) {
                return {
                    "code": res.code === 0 ? 0 : 1,
                    "msg": res.message || "",
                    "count": res.data && res.data.pagination ? res.data.pagination.total : 0,
                    "data": res.data ? res.data.items : []
                };
            }
        });

        table.on('toolbar(record-table)', function(obj) {
            if (obj.event === 'refresh') {
                table.reload('record-table');
            }
        });
    });
</script>
</body>
</html>
