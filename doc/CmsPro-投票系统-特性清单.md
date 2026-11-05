# CmsPro-投票系统-特性清单

> 版本：1.0.0 | 更新日期：2026-11-05
> 应用版本：1.0.0 | 应用 ID：cmspro.polling

---

## 一、应用体系架构

| 特性 | 说明 | 实现位置 |
|------|------|----------|
| 目录结构 | 应用级代码位于 `app/Apps/CmsproPolling/` | `app/Apps/CmsproPolling/` |
| 命名空间 | `App\Apps\CmsproPolling\` | 各 PHP 文件命名空间 |
| 数据库隔离 | 3 张业务表，前缀 `app_cmspro_polling_` | `Migrations/` |
| 服务提供者 | 注册路由/视图/配置合并/钩子/应用级权限中间件 | `ServiceProvider.php` |
| 安装卸载 | 执行迁移 + 配置三向同步 + 种子示例活动 | `Install.php` |
| 配置 | `Config/polling.php` + manifest config_groups | `Config/polling.php`、`manifest.json` |
| 权限 | 4 项权限码 + 应用级权限校验中间件 | `manifest.json` `permissions`、`Http/Middleware/CheckManagePermission.php` |
| 菜单 | 后台 1 目录 2 子菜单 + 用户端 1 菜单 + 官网 1 菜单 | `manifest.json` `menus`/`user_menus`/`home_menus` |
| 依赖 | 无外部应用依赖 | `manifest.json` `dependencies` |
| 应用级测试 | 66 个用例（211 项断言），含内存库安全护栏 | `Tests/` |

## 二、三终端全覆盖

| 终端 | 入口 | 说明 |
|------|------|------|
| 后台管理 | `/admin/cmspro/polling` | 活动管理、投票记录、统计导出 |
| 用户端 | `/user/cmspro/polling` | 我发起的投票、参与记录（受 `allow_user_create` 配置控制） |
| 官网前台 | `/cmspro/polling` | 公开投票列表、详情、游客可投票 |

## 三、路由系统

| 路由组 | 前缀 | 中间件 | 实现位置 |
|--------|------|--------|----------|
| 后台页面 | `/admin/cmspro/polling` | `web`, `auth:admin`, `permission`, `polling.permission` | `Routes/admin.php` |
| 后台 API | `/admin/cmspro/polling` | `web`, `auth:admin`, `permission`, `polling.permission` | `Routes/admin_api.php` |
| 用户页面 | `/user/cmspro/polling` | `web`, `auth:web` | `Routes/user.php` |
| 用户 API | `/api/user/cmspro/polling` | `web`, `auth:web` | `Routes/user_api.php` |
| 前台页面 | `/cmspro/polling` | `web` | `Routes/home.php` |
| 前台 API | `/cmspro/polling/api` | `web`, `throttle:60,1` | `Routes/home_api.php` |

> `polling.permission` 为应用级权限校验中间件（框架 `permission` 仅覆盖 `api/admin/` 前缀）。

## 四、投票类型与计分

| 类型 | code | 规则 | 计分方式 |
|------|------|------|----------|
| 单选投票 | `single` | 恰好选择 1 个选项 | 按票数统计占比 |
| 多选投票 | `multiple` | 1 ~ `max_choices` 个选项 | 按票数统计占比 |
| 排序投票 | `ranking` | 至少对 2 个选项排序 | Borda 计分：每选项得分 = Σ max(0, 选项总数 - 名次)，并统计名次分布 |

## 五、防刷票机制

| 机制 | 字段/配置 | 说明 |
|------|-----------|------|
| 每人限投次数 | `votes_per_user` | 登录用户按用户维度计数，游客按 IP 维度计数 |
| 同 IP 批次上限 | `ip_limit` | 同一 IP 在同一活动下的总提交批次数（含登录用户），0 为不限制 |
| 需登录投票 | `require_login` | 活动级控制游客是否可投 |
| 投票批次号 | `vote_no` | 同一次提交的多条记录共享批次号，事务内 `lockForUpdate` 防并发冲突 |
| 前台限流 | `throttle:60,1` | 前台 API 每分钟最多 60 次请求 |

## 六、数据层

| 表名 | 说明 | 关键字段 |
|------|------|----------|
| `app_cmspro_polling_activities` | 投票活动表 | title、type、max_choices、start_time、end_time、status、require_login、votes_per_user、ip_limit、anonymous、result_visibility、user_id |
| `app_cmspro_polling_options` | 投票选项表 | activity_id、title、description、image、sort |
| `app_cmspro_polling_records` | 投票记录表 | activity_id、option_id、user_id、vote_no、rank、ip |

索引使用 Laravel 自动生成名（含表名前缀），兼容 SQLite 测试内存库与 MySQL 生产库。

数据保护策略：

- **已投票选项保护**：更新已有投票记录的活动时，携带 `id` 的选项原地更新（`option_id` 保持稳定），
  已投票但未被提交的选项强制保留，防止投票记录悬空，返回消息自动提示保留数量；
- **列表分页钳制**：所有列表接口 `per_page` 钳制在 1 ~ 100，防止恶意大分页拖垮查询；
- **记录列表预加载**：后台投票记录查询 `with(['activity', 'option', 'user'])`，消除 N+1；
- **前端输出转义**：所有服务端数据在前端经 `.val()` / `.text()` DOM 赋值输出，杜绝存储型 XSS。

## 七、钩子系统

| 钩子点 | 类型 | 触发时机 | 参数 |
|--------|------|----------|------|
| `polling.vote.submitted` | Action | 投票提交成功后 | `PollingActivity $activity`, `array $records` |
| `polling.activity.stats` | Filter | 统计数据返回前 | `array $stats`, `PollingActivity $activity` |

## 八、配置系统

| 配置项 | code | 默认值 | 说明 |
|--------|------|--------|------|
| 默认每人限投次数 | `app_cmspro_polling_default_votes_per_user` | 1 | 新建活动时预填的每人限投次数 |
| 同 IP 投票限制 | `app_cmspro_polling_ip_limit` | 0 | 新建活动时预填的同 IP 限制，0 为不限制 |
| 允许用户端发起投票 | `app_cmspro_polling_allow_user_create` | 1 | 关闭后仅后台管理员可创建活动 |

消费方式：三个配置项均通过 `ActivityService::getConfig()` 直读数据库（`config_items` 表），
后台「投票设置」页保存后立即生效，无需重启或清缓存；用户端发起投票表单的
限投次数与 IP 限制输入框按配置值预填。

## 九、权限系统

| 权限代码 | 权限名称 | 说明 |
|----------|----------|------|
| `cmspro.polling.manage` | 投票管理 | 投票管理基础权限（后台访问兜底） |
| `cmspro.polling.activity.manage` | 活动管理 | 活动的创建、编辑、删除、状态流转 |
| `cmspro.polling.record.view` | 查看投票记录 | 后台投票记录查询 |
| `cmspro.polling.stats.export` | 导出统计报告 | 统计页 CSV 导出 |

校验实现（`Http/Middleware/CheckManagePermission.php`，参考框架 `CheckPermission` 机制）：

- 超管放行；权限码未注册到 `admin_permissions` 表时放行（与框架一致）；
- 路由与权限的映射：`/records`（页面+接口）→ `record.view`；`/export/` → `stats.export`；
  活动写操作（POST/PUT/PATCH/DELETE `/activities`）→ `activity.manage`；其余 → `manage`；
- 权限不足：AJAX 请求返回 JSON 403（`ApiResponse::error(40101)`），页面请求 abort 403；
- 后台列表页行内「编辑/删除」按钮按 `activity.manage` 权限显隐（`data-permission`）。

## 十、结果可见性

| 模式 | code | 说明 |
|------|------|------|
| 实时可见 | `always` | 任何人随时可看统计 |
| 投票后可见 | `after_vote` | 参与投票后可见 |
| 结束后可见 | `end` | 活动截止后可见 |
| 不公开 | `never` | 仅后台可看 |

## 十一、安装卸载升级

| 操作 | 行为 | 实现位置 |
|------|------|----------|
| 安装 | 执行迁移 + 配置三向同步（manifest 为唯一基准）+ 种子示例活动 | `Install::install()` |
| 卸载 | 回滚迁移 + 清理应用缓存 | `Install::uninstall()` |
| 升级 | 执行迁移 + 配置三向同步 | `Install::upgrade()` |

迁移含兜底机制：系统迁移未执行时直接 require 迁移文件执行 `up()`/`down()`，保证 Windows 等环境下安装可用。

## 十二、视图规范

| 视图目录 | 说明 |
|----------|------|
| `Views/Admin/activities/` | 活动列表、表单（分区布局 + 动态选项行）、统计页 |
| `Views/Admin/records/` | 投票记录列表 |
| `Views/User/` | 我的投票列表、发起/编辑表单、参与记录 |
| `Views/Home/` | 前台列表、详情（核心投票交互）、结果片段 |
| `Views/layouts/home.blade.php` | 前台独立布局（CSS 变量主题 + 登录态判断） |

## 十三、应用级测试

| 测试文件 | 用例数 | 覆盖内容 |
|----------|--------|----------|
| `Tests/Feature/VoteServiceTest.php` | 30 | 三种投票类型校验链、状态/时间/登录拦截、防刷（限投/IP/批次）、Borda 计分、四种结果可见性、钩子触发、CSV 导出脱敏 |
| `Tests/Feature/ActivityServiceTest.php` | 24 | 活动 CRUD、选项携带 ID 原地更新与整体替换、已投票选项强制保留、已有记录禁改类型、越权拦截（limitUserId）、状态流转、级联删除、列表过滤、per_page 钳制、数据库配置读取 |
| `Tests/Feature/HomePollingApiTest.php` | 8 | 前台页面渲染、游客投票 API、参数校验（CMSPRO 统一 40201 格式）、统计 API 可见性控制 |
| `Tests/Feature/UserPollingApiTest.php` | 4 | 用户中心创建/开关拦截（allow_user_create）、普通（非 JSON）表单提交校验失败 302 回归 |
| `Tests/PollingTestCase.php` | 基类 | 内存库安全护栏、应用迁移补跑、数据构造助手 |

运行方式：`php artisan test app/Apps/CmsproPolling/Tests`（锁定 phpunit.xml 的 sqlite :memory:）。

## 十四、版本历史

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| 1.0.0 | 2026-11-05 | 开发团队 | 初始版本：三终端、三种投票类型、防刷票、匿名投票、排序计分、统计导出、应用级测试 |
| 1.0.0 | 2026-11-05 | 开发团队 | 六维度验收整改（同版本内修复）：新增应用级权限校验中间件、存储型 XSS 修复（前端统一 DOM 赋值）、emoji 改 FontAwesome、配置项改数据库直读并接入表单预填、已投票选项删除保护、Layui 分页参数对齐、per_page 钳制、记录列表 N+1 消除、交互细节（防重提交、错误回调解析、iframe 弹层精确关闭、键盘可达性、prefers-reduced-motion）、测试补齐至 66 用例 |
