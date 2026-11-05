# 投票系统 应用文档

## 元信息

| 项 | 值 |
| --- | --- |
| 应用 ID | `cmspro.polling` |
| 应用名称 | 投票系统 |
| 应用版本 | 1.0.0 |
| 文档版本 | 1.0.0 |
| 官方地址 | https://www.cmspro.cn/apps/cmspro.polling |
| 依赖 | PHP >= 8.3，CmsPro >= 5.0.0（无其他应用依赖） |

## 应用概述

多活动用户投票系统，支持单选 / 多选 / 排序（Borda 计分）三种投票类型，内置防刷票（每人限投次数、同 IP 限制、需登录投票）、匿名投票、四种结果可见性策略与实时统计，覆盖后台管理、用户中心、官网前台三端，适用于问卷调查、社区话题决策、活动评选等场景。

核心业务闭环：创建活动（后台/用户中心）→ 前台公开投票（游客或登录用户）→ 实时统计与结果展示 → 统计导出 CSV。

## 目录结构

```
CmsproPolling/
├── Config/             # 应用配置（polling.php）
├── Controllers/
│   ├── Admin/          # 后台控制器（活动、记录、页面）
│   ├── User/           # 用户端控制器（活动、记录、页面）
│   └── Home/           # 前台控制器（列表、详情、投票、统计）
├── doc/                # 应用文档（特性清单、使用指南、扩展指南）
├── Exceptions/         # 业务异常（VoteException）
├── Migrations/         # 数据库迁移（活动、选项、记录三表）
├── Models/             # 模型（PollingActivity、PollingOption、PollingRecord）
├── Routes/             # 路由（admin、admin_api、user、user_api、home、home_api）
├── Services/           # 业务服务（ActivityService、VoteService）
├── Tests/              # 应用级测试（66 用例 / 211 断言）
├── Views/
│   ├── Admin/          # 后台视图（活动列表/表单/统计、记录）
│   ├── Home/           # 前台视图（列表、详情、结果片段）
│   ├── layouts/        # 前台独立布局
│   └── User/           # 用户端视图（我的投票、表单、记录）
├── Hooks.php           # 钩子注册入口
├── Install.php         # 安装脚本（迁移兜底、配置同步、示例活动）
├── manifest.json       # 应用清单（元信息、菜单、配置组、权限）
├── ServiceProvider.php # 服务提供者（路由、视图、配置、钩子）
└── README.md           # 本文档
```

## 快速开始

1. **安装**：后台上传应用包或从应用市场安装，安装脚本自动建表并创建示例活动；
2. **后台**：投票管理 → 活动管理 → 新建活动（配置类型、防刷规则、可见性）；
3. **前台**：访问 `/cmspro/polling` 开始投票；
4. **统计**：活动管理 → 统计 → 导出 CSV。

## 核心特性

| 特性 | 说明 |
|------|------|
| 三种投票类型 | 单选 / 多选（max_choices 上限）/ 排序（Borda 计分 + 名次分布） |
| 防刷票 | 每人限投次数（用户/IP 双维度）、同 IP 批次上限、需登录投票、前台限流 |
| 匿名投票 | 活动级开关，导出与记录自动脱敏 |
| 结果可见性 | 实时可见 / 投票后可见 / 结束后可见 / 不公开 |
| 运行时状态 | 过期活动自动视为已结束，无需手动写库 |
| 统计导出 | CSV（UTF-8 BOM），含汇总与明细 |
| 钩子扩展 | `polling.vote.submitted`（Action）、`polling.activity.stats`（Filter） |

## 应用级测试

```bash
# 在 code 目录下执行（锁定 phpunit.xml 的 sqlite :memory: 内存库）
php artisan test app/Apps/CmsproPolling/Tests
```

覆盖：三种类型投票校验链、防刷票、Borda 计分、结果可见性、钩子触发、越权拦截、级联删除、前台 HTTP 全链路。

## 文档索引

| 文档 | 说明 |
|------|------|
| `doc/CmsPro-投票系统-特性清单.md` | 技术特性全量索引 |
| `doc/CmsPro-投票系统-使用指南.md` | 安装配置与日常使用 |
| `doc/CmsPro-投票系统应用扩展指南.md` | 钩子、服务 API 与二次开发 |

## 更新日志

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.0 | 2026-11-05 | 初始版本 |
