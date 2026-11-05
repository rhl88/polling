<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>投票记录</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
</head>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" action="">
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input type="number" name="activity_id" placeholder="活动ID" class="layui-input">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input type="text" name="ip" placeholder="IP地址" class="layui-input">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input type="text" name="start_date" id="startDate" placeholder="开始日期" class="layui-input" autocomplete="off">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input type="text" name="end_date" id="endDate" placeholder="结束日期" class="layui-input" autocomplete="off">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn layui-btn-md" lay-submit lay-filter="record-query">
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
            <table id="record-table" lay-filter="record-table"></table>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['table', 'form', 'laydate', 'jquery'], function() {
        var table = layui.table;
        var form = layui.form;
        var laydate = layui.laydate;
        var $ = layui.jquery;

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        laydate.render({ elem: '#startDate' });
        laydate.render({ elem: '#endDate' });

        table.render({
            elem: '#record-table',
            url: '/admin/cmspro/polling/api/records',
            page: {
                layout: ['count', 'prev', 'page', 'next', 'limit'],
                groups: 5,
                limit: 20,
                limits: [10, 20, 50, 100]
            },
            cols: [[
                { title: 'ID', field: 'id', align: 'center', width: 80, sort: true },
                { title: '活动ID', field: 'activity_id', align: 'center', width: 90, sort: true },
                { title: '活动标题', field: 'activity_title', align: 'left', minWidth: 180 },
                { title: '选项', field: 'option_title', align: 'left', minWidth: 150 },
                { title: '用户名', field: 'username', align: 'center', width: 110 },
                { title: '昵称', field: 'nickname', align: 'center', width: 110 },
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

        form.on('submit(record-query)', function(data) {
            table.reload('record-table', { where: data.field, page: { curr: 1 } });
            return false;
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
