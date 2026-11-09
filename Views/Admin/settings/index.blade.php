<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>投票设置</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
</head>
<style>
    .settings-section { margin-bottom: 20px; }
    .settings-section .section-title { font-size: 16px; font-weight: 600; padding: 10px 0; border-bottom: 2px solid #1e9fff; margin-bottom: 15px; }
    .settings-item { margin-bottom: 15px; }
    .settings-item .item-label { display: inline-block; width: 180px; text-align: right; padding-right: 10px; color: #333; font-weight: 500; }
    .settings-item .item-tips { color: #999; font-size: 12px; margin-top: 4px; padding-left: 190px; }
    .settings-item .layui-input { width: 400px; }
    .settings-item .layui-form-switch { margin-top: 6px; }
    .btn-area { padding: 20px 0 20px 190px; }
</style>
<body>
<div class="pear-container" style="padding: 15px;">
    <form class="layui-form" lay-filter="settingsForm">
        <div class="layui-card">
            <div class="layui-card-body" style="padding: 20px;">

                <div class="settings-section">
                    <div class="section-title">投票规则</div>

                    <div class="settings-item">
                        <label class="item-label">默认每人限投次数</label>
                        <input type="number" name="default_votes_per_user" class="layui-input" style="display:inline-block;" min="0" placeholder="请输入次数">
                        <div class="item-tips">新建活动时预填的每人限投次数</div>
                    </div>

                    <div class="settings-item">
                        <label class="item-label">同IP投票限制</label>
                        <input type="number" name="ip_limit" class="layui-input" style="display:inline-block;" min="0" placeholder="0 为不限制">
                        <div class="item-tips">同一IP在同一活动下最多可提交投票的次数，0为不限制</div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="section-title">权限控制</div>

                    <div class="settings-item">
                        <label class="item-label">允许用户端发起投票</label>
                        <input type="checkbox" name="allow_user_create" lay-skin="switch" lay-text="开启|关闭" lay-filter="allow_user_create">
                        <div class="item-tips">开启后用户可在用户中心发起投票；关闭后仅后台管理员可创建</div>
                    </div>
                </div>

                <div class="btn-area">
                    <button class="layui-btn" lay-submit lay-filter="saveSettings"><i class="layui-icon layui-icon-ok"></i> 保存设置</button>
                </div>

            </div>
        </div>
    </form>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['form', 'jquery', 'layer'], function() {
        var form = layui.form;
        var $ = layui.jquery;
        var layer = layui.layer;

        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            statusCode: {
                401: function() {
                    layer.msg('登录已过期，请重新登录', { icon: 2 });
                    setTimeout(function() { location.href = '/admin/login'; }, 800);
                }
            }
        });

        // 加载当前配置并回填表单
        function loadSettings() {
            $.ajax({
                url: '/admin/cmspro/polling/api/settings',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.code === 0 && res.data) {
                        var d = res.data;
                        $('input[name="default_votes_per_user"]').val(d.default_votes_per_user);
                        $('input[name="ip_limit"]').val(d.ip_limit);
                        $('input[name="allow_user_create"]').prop('checked', d.allow_user_create == '1' || d.allow_user_create === true);
                        form.render();
                    }
                }
            });
        }

        loadSettings();

        // 保存设置
        form.on('submit(saveSettings)', function(data) {
            var field = data.field;

            // switch 未勾选时不会提交，需手动补 0/1
            field.allow_user_create = field.allow_user_create ? '1' : '0';

            var loading = layer.load();
            $.ajax({
                url: '/admin/cmspro/polling/api/settings',
                type: 'PUT',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({ settings: field }),
                success: function(res) {
                    layer.close(loading);
                    if (res.code === 0) {
                        layer.msg('保存成功', { icon: 1, time: 1500 });
                    } else {
                        layer.msg(res.message || '保存失败', { icon: 2, time: 1500 });
                    }
                },
                error: function() {
                    layer.close(loading);
                    layer.msg('请求失败', { icon: 2, time: 1500 });
                }
            });
            return false;
        });
    });
</script>
</body>
</html>
