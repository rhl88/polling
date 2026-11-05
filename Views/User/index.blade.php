<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>我的投票</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .polling-page { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .page-header .title { font-size: 20px; font-weight: bold; color: #333; }
        .nav-tabs { margin-bottom: 16px; }
        .nav-tabs a {
            display: inline-block;
            padding: 8px 20px;
            margin-right: 6px;
            border-radius: 20px;
            font-size: 14px;
            color: #666;
            background: #f2f2f2;
            text-decoration: none;
            transition: all .2s;
        }
        .nav-tabs a.active, .nav-tabs a:hover { color: #fff; background: #1e9fff; }
        .activity-card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 22px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-card .info .title { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .activity-card .info .meta { font-size: 12px; color: #999; }
        .activity-card .info .meta span { margin-right: 12px; }
        .activity-card .actions .layui-btn { margin-left: 6px; }
        .badge-type { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 12px; margin-right: 8px; }
        .badge-type.single { background: #e8f4ff; color: #1e9fff; }
        .badge-type.multiple { background: #e6fffb; color: #16b1a1; }
        .badge-type.ranking { background: #f0e8ff; color: #8b5cf6; }
        .empty-tip { text-align: center; color: #bbb; padding: 50px 0; font-size: 14px; }
    </style>
</head>
<body>
<div class="polling-page">
    <div class="page-header">
        <div class="title"><i class="fa fa-check-square-o"></i> 我的投票</div>
        <button class="layui-btn" id="btn-create"><i class="layui-icon layui-icon-add-1"></i> 发起投票</button>
    </div>

    <div class="nav-tabs">
        <a href="/user/cmspro/polling" class="active">我发起的</a>
        <a href="/user/cmspro/polling/records">我的参与记录</a>
    </div>

    <div id="activity-list"></div>
    <div id="page-box"></div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['laypage', 'layer', 'jquery'], function() {
        var laypage = layui.laypage;
        var layer = layui.layer;
        var $ = layui.jquery;

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        var currentPage = 1;
        var typeText = { single: '单选投票', multiple: '多选投票', ranking: '排序投票' };
        var statusText = { 0: '草稿', 1: '进行中', 2: '已结束' };

        function loadList(page) {
            currentPage = page || 1;
            $.get('/api/user/cmspro/polling/activities', { page: currentPage, per_page: 10 }, function(r) {
                if (r.code !== 0) {
                    layer.msg(r.message || '加载失败', { icon: 2 });
                    return;
                }

                var items = r.data.items || [];
                var html = '';

                if (items.length === 0) {
                    html = '<div class="empty-tip">还没有发起过投票，点击右上角「发起投票」创建第一个吧～</div>';
                } else {
                    $.each(items, function(i, d) {
                        var statusBadge = d.effective_status === 1
                            ? '<span class="layui-badge layui-bg-green">' + statusText[d.effective_status] + '</span>'
                            : (d.effective_status === 2
                                ? '<span class="layui-badge layui-bg-orange">' + statusText[d.effective_status] + '</span>'
                                : '<span class="layui-badge layui-bg-gray">' + statusText[d.effective_status] + '</span>');

                        html += '<div class="activity-card" data-id="' + d.id + '">' +
                            '<div class="info">' +
                                '<div class="title"><span class="badge-type ' + d.type + '">' + typeText[d.type] + '</span>' + d.title + '</div>' +
                                '<div class="meta">' +
                                    '<span>' + statusBadge + '</span>' +
                                    '<span><i class="fa fa-bar-chart"></i> ' + d.records_count + ' 票</span>' +
                                    '<span>' + (d.start_time ? d.start_time : '不限') + ' ~ ' + (d.end_time ? d.end_time : '不限') + '</span>' +
                                '</div>' +
                            '</div>' +
                            '<div class="actions">' +
                                '<button class="layui-btn layui-btn-xs" data-action="view">预览</button>' +
                                '<button class="layui-btn layui-btn-xs layui-btn-normal" data-action="edit">编辑</button>' +
                                '<button class="layui-btn layui-btn-xs layui-btn-danger" data-action="delete">删除</button>' +
                            '</div>' +
                        '</div>';
                    });
                }

                $('#activity-list').html(html);

                // 分页
                var pagination = r.data.pagination;
                if (pagination && pagination.total > pagination.per_page) {
                    laypage.render({
                        elem: 'page-box',
                        count: pagination.total,
                        limit: pagination.per_page,
                        curr: pagination.current_page,
                        jump: function(obj, first) {
                            if (!first) {
                                loadList(obj.curr);
                            }
                        }
                    });
                } else {
                    $('#page-box').empty();
                }
            });
        }

        loadList(1);

        // 发起投票
        $('#btn-create').on('click', function() {
            location.href = '/user/cmspro/polling/create';
        });

        // 卡片操作
        $('#activity-list').on('click', 'button[data-action]', function() {
            var card = $(this).closest('.activity-card');
            var id = card.data('id');
            var action = $(this).data('action');

            if (action === 'view') {
                window.open('/cmspro/polling/' + id);
                return;
            }

            if (action === 'edit') {
                location.href = '/user/cmspro/polling/edit/' + id;
                return;
            }

            if (action === 'delete') {
                layer.confirm('确定删除该投票？相关选项与记录将一并删除。', { icon: 3 }, function(index) {
                    $.ajax({
                        url: '/api/user/cmspro/polling/activities/' + id,
                        method: 'DELETE',
                        success: function(r) {
                            if (r.code === 0) {
                                layer.msg('删除成功', { icon: 1 });
                                loadList(currentPage);
                            } else {
                                layer.msg(r.message || '删除失败', { icon: 2 });
                            }
                        }
                    });
                    layer.close(index);
                });
            }
        });
    });
</script>
</body>
</html>
