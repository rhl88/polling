<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>我的参与记录</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .polling-page { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .page-header .title { font-size: 20px; font-weight: bold; color: #333; margin-bottom: 16px; }
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
            transition: color .2s, background-color .2s;
        }
        .nav-tabs a.active, .nav-tabs a:hover { color: #fff; background: #1e9fff; }
        .record-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px 22px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .record-card .title { font-size: 15px; font-weight: 600; color: #333; margin-bottom: 6px; }
        .record-card .meta { font-size: 12px; color: #999; }
        .record-card .meta span { margin-right: 14px; }
        .empty-tip { text-align: center; color: #bbb; padding: 50px 0; font-size: 14px; }
    </style>
</head>
<body>
<div class="polling-page">
    <div class="page-header">
        <div class="title"><i class="fa fa-history"></i> 我的参与记录</div>
    </div>

    <div class="nav-tabs">
        <a href="/user/cmspro/polling">我发起的</a>
        <a href="/user/cmspro/polling/records" class="active">我的参与记录</a>
    </div>

    <div id="record-list"></div>
    <div id="page-box"></div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['laypage', 'layer', 'jquery'], function() {
        var laypage = layui.laypage;
        var layer = layui.layer;
        var $ = layui.jquery;

        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            statusCode: {
                401: function() {
                    layer.msg('登录已过期，请重新登录', { icon: 2 });
                    setTimeout(function() { location.href = '/user/login'; }, 800);
                }
            }
        });

        function loadList(page) {
            $.get('/api/user/cmspro/polling/records', { page: page || 1, per_page: 15 }, function(r) {
                if (r.code !== 0) {
                    layer.msg(r.message || '加载失败', { icon: 2 });
                    return;
                }

                var items = r.data.items || [];
                var $list = $('#record-list').empty();

                if (items.length === 0) {
                    $('<div>')
                        .addClass('empty-tip')
                        .html('暂无参与记录，去 <a href="/cmspro/polling" style="color:#1e9fff;">投票广场</a> 看看吧～')
                        .appendTo($list);
                } else {
                    // XSS 防护：服务端数据统一 .text() 赋值，不做字符串拼接
                    $.each(items, function(i, d) {
                        var $card = $('<div>').addClass('record-card').data('id', d.activity_id);

                        $('<div>').addClass('title').text(d.activity_title || '').appendTo($card);

                        var $meta = $('<div>').addClass('meta');
                        var $voteTo = $('<span>').text('投给：');
                        $('<b>').css('color', '#333').text(d.option_title || '').appendTo($voteTo);
                        $voteTo.appendTo($meta);

                        if (d.rank > 0) {
                            $('<span>').text('名次：第 ' + d.rank + ' 名').appendTo($meta);
                        }
                        $('<span>').text('时间：' + d.create_time).appendTo($meta);

                        $card.append($meta).appendTo($list);
                    });
                }

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

        // 点击卡片跳转到活动详情
        $('#record-list').on('click', '.record-card', function() {
            window.open('/cmspro/polling/' + $(this).data('id'));
        });
    });
</script>
</body>
</html>
