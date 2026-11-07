<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>活动管理</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    @include('admin.partials.permission-script')
    <style>
        /* layui table render 后 DOM 移出原 elem，选择器须用 .layui-table 类而非 #activity-table */
        .layui-table .title-link { color: #1e9fff; }
        .layui-table .title-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" action="">
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input type="text" name="keyword" placeholder="活动标题" class="layui-input">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <select name="status">
                            <option value="">全部状态</option>
                            <option value="0">草稿</option>
                            <option value="1">进行中</option>
                            <option value="2">已结束</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <select name="type">
                            <option value="">全部类型</option>
                            <option value="single">单选投票</option>
                            <option value="multiple">多选投票</option>
                            <option value="ranking">排序投票</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn layui-btn-md" lay-submit lay-filter="activity-query">
                        <i class="layui-icon layui-icon-search"></i> 查询
                    </button>
                    <button type="reset" class="layui-btn layui-btn-md layui-btn-primary">
                        <i class="layui-icon layui-icon-refresh"></i> 重置
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-body">
            <div style="margin-bottom: 10px;">
                <button class="layui-btn layui-btn-sm" id="btn-add" data-permission="cmspro.polling.activity.manage">
                    <i class="layui-icon layui-icon-add-1"></i> 新建活动
                </button>
            </div>
            <table id="activity-table" lay-filter="activity-table"></table>
        </div>
    </div>
</div>

{{-- Layui 模板：状态徽章 --}}
@verbatim
<script type="text/html" id="statusTpl">
    {{# if(d.effective_status === 0){ }}
    <span class="layui-badge layui-bg-gray">草稿</span>
    {{# } else if(d.effective_status === 1){ }}
    <span class="layui-badge layui-bg-green">进行中</span>
    {{# } else { }}
    <span class="layui-badge layui-bg-orange">已结束</span>
    {{# } }}
</script>
@endverbatim

{{-- Layui 模板：类型徽章 --}}
@verbatim
<script type="text/html" id="typeTpl">
    {{# if(d.type === 'ranking'){ }}
    <span class="layui-badge layui-bg-blue">{{ d.type_text }}</span>
    {{# } else if(d.type === 'multiple'){ }}
    <span class="layui-badge layui-bg-cyan">{{ d.type_text }}</span>
    {{# } else { }}
    <span class="layui-badge layui-badge-rim">{{ d.type_text }}</span>
    {{# } }}
</script>
@endverbatim

{{-- Layui 模板：行操作按钮（无活动管理权限时隐藏编辑/删除） --}}
@verbatim
<script type="text/html" id="rowBar">
    <a class="layui-btn layui-btn-xs" lay-event="stats">统计</a>
    {{# if(!window.hasPermission || window.hasPermission('cmspro.polling.activity.manage')){ }}
    <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="edit">编辑</a>
    <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="delete">删除</a>
    {{# } }}
</script>
@endverbatim

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['table', 'form', 'layer', 'jquery'], function() {
        var table = layui.table;
        var form = layui.form;
        var layer = layui.layer;
        var $ = layui.jquery;

        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            statusCode: {
                401: function() {
                    layer.msg('登录已过期，请重新登录', { icon: 2 });
                    setTimeout(function() { location.href = '/admin/login'; }, 800);
                }
            }
        });

        // 隐藏无权限按钮
        $('button[data-permission]').each(function() {
            if (window.hasPermission && !window.hasPermission($(this).data('permission'))) {
                $(this).hide();
            }
        });

        // HTML 转义：标题为用户可控数据，拼接进 templet 前必须转义（XSS 防护原则）
        function escapeHtml(str) {
            return String(str == null ? '' : str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        var cols = [[
            { title: 'ID', field: 'id', align: 'center', width: 70, sort: true },
            { title: '活动标题', field: 'title', align: 'left', minWidth: 180, templet: function(d) {
                return '<a class="title-link" href="/cmspro/polling/' + d.id + '" target="_blank" rel="noopener"'
                    + ' title="' + escapeHtml(d.title) + '（点击在新窗口查看前台详情）">'
                    + escapeHtml(d.title) + '</a>';
            } },
            { title: '类型', field: 'type_text', align: 'center', width: 100, templet: '#typeTpl' },
            { title: '状态', field: 'effective_status_text', align: 'center', width: 90, templet: '#statusTpl' },
            { title: '票数', field: 'records_count', align: 'center', width: 80, sort: true },
            { title: '开始时间', field: 'start_time', align: 'center', width: 165 },
            { title: '结束时间', field: 'end_time', align: 'center', width: 165 },
            { title: '创建时间', field: 'create_time', align: 'center', width: 165 },
            { title: '操作', align: 'center', width: 200, toolbar: '#rowBar', fixed: 'right' }
        ]];

        table.render({
            elem: '#activity-table',
            url: '/admin/cmspro/polling/api/activities',
            // Layui 默认发 limit，后端读 per_page，需显式对齐参数名（常见问题第十三章）
            request: { pageName: 'page', limitName: 'per_page' },
            page: {
                layout: ['count', 'prev', 'page', 'next', 'limit'],
                groups: 5,
                limit: 20,
                limits: [10, 20, 50, 100]
            },
            cols: cols,
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

        // 新建活动
        $('#btn-add').on('click', function() {
            layer.open({
                type: 2,
                title: '新建投票活动',
                area: ['90%', '90%'],
                content: '/admin/cmspro/polling/activities/form',
                shade: 0.3,
                shadeClose: false,
                closeBtn: 1,
                end: function() {
                    // 弹层关闭后刷新列表，捕获「保存成功但消息提示未及展示」的边缘场景
                    table.reload('activity-table');
                }
            });
        });

        form.on('submit(activity-query)', function(data) {
            table.reload('activity-table', { where: data.field, page: { curr: 1 } });
            return false;
        });

        table.on('toolbar(activity-table)', function(obj) {
            if (obj.event === 'refresh') {
                table.reload('activity-table');
            }
        });

        table.on('tool(activity-table)', function(obj) {
            var data = obj.data;

            if (obj.event === 'stats') {
                layer.open({
                    type: 2,
                    title: '投票统计',
                    area: ['90%', '90%'],
                    content: '/admin/cmspro/polling/activities/stats/' + data.id,
                    shade: 0.3,
                    shadeClose: false,
                    closeBtn: 1
                });
                return;
            }

            if (obj.event === 'edit') {
                layer.open({
                    type: 2,
                    title: '编辑投票活动',
                    area: ['90%', '90%'],
                    content: '/admin/cmspro/polling/activities/form/' + data.id,
                    shade: 0.3,
                    shadeClose: false,
                    closeBtn: 1,
                    end: function() {
                        // 编辑弹层关闭后刷新列表（含取消编辑的情况，保持数据新鲜）
                        table.reload('activity-table');
                    }
                });
                return;
            }

            if (obj.event === 'delete') {
                layer.confirm('确定删除该投票活动？活动下的选项与投票记录将一并删除，操作不可恢复。', { icon: 3, title: '删除确认' }, function(index) {
                    $.ajax({
                        url: '/admin/cmspro/polling/api/activities/' + data.id,
                        method: 'DELETE',
                        success: function(r) {
                            if (r.code === 0) {
                                layer.msg('删除成功', { icon: 1 });
                                table.reload('activity-table');
                            } else {
                                layer.msg(r.message || '删除失败', { icon: 2 });
                            }
                        },
                        error: function(xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) || '删除请求失败，请稍后重试';
                            layer.msg(msg, { icon: 2 });
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
