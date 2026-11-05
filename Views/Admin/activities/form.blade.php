<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>投票活动编辑</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        .layui-form {
            height: 100%;
        }
        /* 内容区：总高度减去底部按钮栏高度，内容超出时自身滚动 */
        .layui-form > .layui-row {
            height: calc(100vh - 52px);
            overflow-y: auto;
            padding: 15px;
            margin: 0;
        }
        /* 底部按钮栏：固定 52px 高度，始终贴底不参与滚动 */
        .bottom {
            height: 52px;
            background: #fff;
            border-top: 1px solid #e6e6e6;
            text-align: right;
        }
        .bottom .button-container {
            padding: 10px 15px;
        }
        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin: 15px 0 10px;
            padding-left: 8px;
            border-left: 3px solid #1e9fff;
        }
        .section-title:first-child {
            margin-top: 0;
        }
        .option-row {
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
            background: #fafafa;
        }
        .option-row .layui-inline {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .option-index {
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            text-align: center;
            background: #1e9fff;
            color: #fff;
            border-radius: 50%;
            font-size: 12px;
        }
        .tips-text {
            color: #999;
            font-size: 12px;
            line-height: 20px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<form class="layui-form" lay-filter="activityForm">
    <div class="layui-row">
        <div class="section-title">基本信息</div>
        <div class="layui-form-item">
            <label class="layui-form-label">活动标题</label>
            <div class="layui-input-block">
                <input type="text" name="title" lay-verify="required" maxlength="200" placeholder="请输入活动标题" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">活动描述</label>
            <div class="layui-input-block">
                <textarea name="description" placeholder="请输入活动描述（选填）" class="layui-textarea" maxlength="5000"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">投票类型</label>
            <div class="layui-input-block">
                <input type="radio" name="type" value="single" title="单选投票" checked>
                <input type="radio" name="type" value="multiple" title="多选投票">
                <input type="radio" name="type" value="ranking" title="排序投票">
            </div>
        </div>
        <div class="layui-form-item" id="maxChoicesItem" style="display:none;">
            <label class="layui-form-label">最多可选数</label>
            <div class="layui-input-block">
                <input type="number" name="max_choices" value="2" min="2" class="layui-input" style="width:150px;">
                <span class="tips-text">多选投票时每位用户最多可同时选择的选项数</span>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">活动状态</label>
            <div class="layui-input-block">
                <input type="radio" name="status" value="0" title="草稿">
                <input type="radio" name="status" value="1" title="进行中" checked>
                <input type="radio" name="status" value="2" title="已结束">
            </div>
        </div>

        <div class="section-title">时间设置</div>
        <div class="layui-form-item">
            <label class="layui-form-label">开始时间</label>
            <div class="layui-input-block">
                <input type="text" name="start_time" id="startTime" placeholder="不填表示立即开始" class="layui-input" style="width:220px;" autocomplete="off">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">结束时间</label>
            <div class="layui-input-block">
                <input type="text" name="end_time" id="endTime" placeholder="不填表示不设截止" class="layui-input" style="width:220px;" autocomplete="off">
                <span class="tips-text">到达结束时间后活动自动停止投票</span>
            </div>
        </div>

        <div class="section-title">投票规则</div>
        <div class="layui-form-item">
            <label class="layui-form-label">需要登录</label>
            <div class="layui-input-block">
                <input type="checkbox" name="require_login" lay-skin="switch" lay-text="是|否" value="1">
                <span class="tips-text">开启后未登录用户不可投票</span>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">每人限投次数</label>
            <div class="layui-input-block">
                <input type="number" name="votes_per_user" value="1" min="1" class="layui-input" style="width:150px;">
                <span class="tips-text">同一用户在该活动下最多可提交投票的次数</span>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">同IP投票限制</label>
            <div class="layui-input-block">
                <input type="number" name="ip_limit" value="0" min="0" class="layui-input" style="width:150px;">
                <span class="tips-text">同一IP在该活动下最多可提交投票的次数，0 表示不限制（防刷票）</span>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">匿名投票</label>
            <div class="layui-input-block">
                <input type="checkbox" name="anonymous" lay-skin="switch" lay-text="是|否" value="1">
                <span class="tips-text">开启后统计报告与记录导出中不展示投票人信息</span>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">结果可见性</label>
            <div class="layui-input-block">
                <select name="result_visibility">
                    <option value="always">实时可见</option>
                    <option value="after_vote">投票后可见</option>
                    <option value="end">结束后可见</option>
                    <option value="never">不公开</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">排序</label>
            <div class="layui-input-block">
                <input type="number" name="sort" value="0" min="0" class="layui-input" style="width:150px;">
                <span class="tips-text">数值越小越靠前</span>
            </div>
        </div>

        <div class="section-title">投票选项</div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button type="button" class="layui-btn layui-btn-sm layui-btn-normal" id="btn-add-option">
                    <i class="layui-icon layui-icon-add-1"></i> 添加选项
                </button>
                <span class="tips-text">至少填写 2 个选项；勾选类投票建议不超过 20 个</span>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block" id="option-list"></div>
        </div>
    </div>

    <div class="bottom">
        <div class="button-container">
            <button type="submit" class="layui-btn layui-btn-normal layui-btn-sm" lay-submit lay-filter="save">
                <i class="layui-icon layui-icon-ok"></i> 提交
            </button>
            <button type="reset" class="layui-btn layui-btn-primary layui-btn-sm">
                <i class="layui-icon layui-icon-refresh"></i> 清空
            </button>
        </div>
    </div>
</form>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['form', 'layer', 'laydate', 'jquery'], function() {
        var form = layui.form;
        var layer = layui.layer;
        var laydate = layui.laydate;
        var $ = layui.jquery;

        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        var activityId = '{{ $activityId }}';
        var isEdit = activityId !== '';
        var typeChanged = false;

        laydate.render({ elem: '#startTime', type: 'datetime' });
        laydate.render({ elem: '#endTime', type: 'datetime' });

        // ---------- 选项动态管理 ----------
        var optionIndex = 0;

        function buildOptionRow(option) {
            option = option || {};
            optionIndex++;
            var row = $(
                '<div class="option-row" data-option-index="' + optionIndex + '">' +
                    '<span class="option-index">' + optionIndex + '</span> ' +
                    '<div class="layui-inline" style="width:260px;">' +
                        '<input type="text" name="option_title_' + optionIndex + '" value="' + (option.title || '') + '" maxlength="200" placeholder="选项标题（必填）" class="layui-input">' +
                    '</div>' +
                    '<div class="layui-inline" style="width:240px;">' +
                        '<input type="text" name="option_desc_' + optionIndex + '" value="' + (option.description || '') + '" maxlength="500" placeholder="选项描述（选填）" class="layui-input">' +
                    '</div>' +
                    '<div class="layui-inline" style="width:220px;">' +
                        '<input type="text" name="option_image_' + optionIndex + '" value="' + (option.image || '') + '" maxlength="255" placeholder="选项图片URL（选填）" class="layui-input">' +
                    '</div>' +
                    '<div class="layui-inline">' +
                        '<button type="button" class="layui-btn layui-btn-xs" data-action="up"><i class="layui-icon layui-icon-up"></i></button>' +
                        '<button type="button" class="layui-btn layui-btn-xs" data-action="down"><i class="layui-icon layui-icon-down"></i></button>' +
                        '<button type="button" class="layui-btn layui-btn-xs layui-btn-danger" data-action="remove"><i class="layui-icon layui-icon-delete"></i></button>' +
                    '</div>' +
                '</div>'
            );

            row.on('click', 'button[data-action]', function() {
                var action = $(this).data('action');
                if (action === 'remove') {
                    if ($('#option-list .option-row').length <= 2) {
                        layer.msg('至少保留 2 个选项', { icon: 2 });
                        return;
                    }
                    row.remove();
                    refreshOptionIndexes();
                } else if (action === 'up') {
                    var prev = row.prev('.option-row');
                    if (prev.length) {
                        row.insertBefore(prev);
                        refreshOptionIndexes();
                    }
                } else if (action === 'down') {
                    var next = row.next('.option-row');
                    if (next.length) {
                        row.insertAfter(next);
                        refreshOptionIndexes();
                    }
                }
            });

            $('#option-list').append(row);
            return row;
        }

        function refreshOptionIndexes() {
            $('#option-list .option-row').each(function(i) {
                $(this).find('.option-index').text(i + 1);
            });
        }

        function collectOptions() {
            var options = [];
            $('#option-list .option-row').each(function(i) {
                var row = $(this);
                var title = $.trim(row.find('input[name^="option_title_"]').val() || '');
                if (title === '') {
                    return;
                }
                options.push({
                    title: title,
                    description: $.trim(row.find('input[name^="option_desc_"]').val() || ''),
                    image: $.trim(row.find('input[name^="option_image_"]').val() || ''),
                    sort: i + 1
                });
            });
            return options;
        }

        $('#btn-add-option').on('click', function() {
            buildOptionRow();
        });

        // ---------- 类型切换控制 ----------
        form.on('radio(type)', function(data) {
            typeChanged = true;
            $('#maxChoicesItem').toggle(data.value === 'multiple');
        });

        // ---------- 编辑模式回填 ----------
        if (isEdit) {
            $.get('/admin/cmspro/polling/api/activities/' + activityId, function(r) {
                if (r.code === 0 && r.data) {
                    var d = r.data;

                    form.val('activityForm', {
                        title: d.title,
                        description: d.description || '',
                        type: d.type,
                        max_choices: d.max_choices || 2,
                        status: String(d.status),
                        require_login: d.require_login === 1 ? 1 : 0,
                        votes_per_user: d.votes_per_user || 1,
                        ip_limit: d.ip_limit || 0,
                        anonymous: d.anonymous === 1 ? 1 : 0,
                        result_visibility: d.result_visibility || 'always',
                        sort: d.sort || 0
                    });

                    // 时间字段单独赋值
                    $('input[name="start_time"]').val(d.start_time || '');
                    $('input[name="end_time"]').val(d.end_time || '');

                    // switch 类组件需手动设置勾选状态
                    if (d.require_login === 1) {
                        $('input[name="require_login"]').prop('checked', true);
                    } else {
                        $('input[name="require_login"]').removeAttr('checked').prop('checked', false);
                    }
                    if (d.anonymous === 1) {
                        $('input[name="anonymous"]').prop('checked', true);
                    } else {
                        $('input[name="anonymous"]').removeAttr('checked').prop('checked', false);
                    }

                    $('#maxChoicesItem').toggle(d.type === 'multiple');

                    // 回填选项
                    $('#option-list').empty();
                    $.each(d.options || [], function(i, option) {
                        buildOptionRow(option);
                    });

                    form.render();
                } else {
                    layer.msg(r.message || '加载失败', { icon: 2 });
                }
            });
        } else {
            // 新建模式：预置 3 个空选项
            buildOptionRow();
            buildOptionRow();
            buildOptionRow();
        }

        // ---------- 提交 ----------
        form.on('submit(save)', function(data) {
            var field = data.field;

            var options = collectOptions();
            if (options.length < 2) {
                layer.msg('至少填写 2 个有效选项（选项标题必填）', { icon: 2 });
                return false;
            }

            var payload = {
                title: field.title,
                description: field.description || '',
                type: field.type,
                max_choices: field.type === 'multiple' ? parseInt(field.max_choices || 2, 10) : 1,
                start_time: field.start_time || '',
                end_time: field.end_time || '',
                status: parseInt(field.status, 10),
                require_login: field.require_login === '1' ? 1 : 0,
                votes_per_user: parseInt(field.votes_per_user || 1, 10),
                ip_limit: parseInt(field.ip_limit || 0, 10),
                anonymous: field.anonymous === '1' ? 1 : 0,
                result_visibility: field.result_visibility,
                sort: parseInt(field.sort || 0, 10),
                options: options
            };

            var url = isEdit
                ? '/admin/cmspro/polling/api/activities/' + activityId
                : '/admin/cmspro/polling/api/activities';
            var method = isEdit ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                method: method,
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function(r) {
                    if (r.code === 0) {
                        layer.msg(r.message || '保存成功', { icon: 1 });
                        setTimeout(function() {
                            parent.layui.table.reload('activity-table');
                            parent.layer.closeAll();
                        }, 800);
                    } else {
                        layer.msg(r.message || '保存失败', { icon: 2 });
                    }
                },
                error: function() {
                    layer.msg('请求失败', { icon: 2 });
                }
            });
            return false;
        });
    });
</script>
</body>
</html>
