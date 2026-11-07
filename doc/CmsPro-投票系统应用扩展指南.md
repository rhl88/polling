# CmsPro-投票系统应用扩展指南

> 版本：1.0.0 | 更新日期：2026-11-05
> 适用应用版本：1.0.0 | 应用 ID：cmspro.polling

---

## 一、扩展方式总览

| 扩展方式 | 适用场景 | 侵入性 |
|----------|----------|--------|
| 监听 Action 钩子 `polling.vote.submitted` | 投票成功后的联动（积分奖励、消息通知、数据同步） | 零侵入 |
| 监听 Filter 钩子 `polling.activity.stats` | 加工统计数据（追加维度、改写展示） | 零侵入 |
| 调用 Service/Model API | 读写活动与投票数据（其他应用、队列任务、命令行） | 只读为主 |
| 新增投票类型 / 新增字段 | 二次开发（需改本应用代码，注意升级兼容） | 侵入 |

## 二、钩子接入

### 2.1 投票提交钩子（Action）

**钩子名**：`polling.vote.submitted`

投票事务提交成功后触发。参数：

| 参数 | 类型 | 说明 |
|------|------|------|
| `$activity` | `PollingActivity` | 投票目标活动 |
| `$records` | `array<PollingRecord>` | 本批次写入的记录（含 option_id、user_id、vote_no、rank、ip） |

**注册示例**（在扩展应用的 `Hooks::register()` 中）：

```php
use App\Apps\CmsproPolling\Models\PollingRecord;
use App\Services\HookManager;

public function register(HookManager $hooks): void
{
    $hooks->registerAction('polling.vote.submitted', function ($activity, array $records) {
        // 示例：给投票用户发积分（假设存在积分服务）
        if (empty($records) || $records[0]->user_id <= 0) {
            return; // 游客投票跳过
        }
        app(\App\Apps\Points\Services\PointsService::class)->grant(
            $records[0]->user_id,
            1,
            '参与投票：' . $activity->title
        );
    }, 10, 'your.app.id');
}
```

> 注意：`registerAction` 第 4 个参数必须传你应用的 app_id（CMSPRO 规范，用于故障隔离与卸载清理）。
> 钩子在事务**提交成功后**触发，回调内异常由 HookManager 捕获记录，不会中断投票流程。

### 2.2 统计加工钩子（Filter）

**钩子名**：`polling.activity.stats`

`VoteService::getStats()` 返回前触发，用于追加或改写统计数据。参数：

| 参数 | 类型 | 说明 |
|------|------|------|
| `$stats` | `array` | 统计结构（见 3.2） |
| `$activity` | `PollingActivity` | 目标活动 |

**注册示例**：

```php
$hooks->registerFilter('polling.activity.stats', function (array $stats, $activity) {
    // 示例：追加「我的选择」标记
    $stats['custom_note'] = '本次活动共 ' . $stats['total_votes'] . ' 人参与';

    return $stats; // 必须返回加工后的数组
}, 10, 'your.app.id');
```

> 该钩子同时影响后台统计页、前台详情页与统计 API 的输出。若消费方依赖固定结构，
> 请只**追加**键名带应用前缀的字段（如 `yourapp_xxx`），不要改动既有键。

## 三、服务 API

### 3.1 VoteService（投票核心）

```php
use App\Apps\CmsproPolling\Services\VoteService;

$voteService = app(VoteService::class);
```

| 方法 | 签名 | 说明 |
|------|------|------|
| 提交投票 | `submitVote(PollingActivity $activity, array $optionIds, array $rankMap = [], ?User $user = null, string $ip = ''): array` | 完整校验链 + 事务写入；返回 ApiResponse 数组（`code=0` 成功）；ranking 类型 `$rankMap` 可传 `[选项ID => 名次]`，为空按 `$optionIds` 顺序生成名次 |
| 统计 | `getStats(PollingActivity $activity): array` | 触发 `polling.activity.stats` Filter 后返回 |
| 结果可见 | `canViewResult(PollingActivity $activity, ?User $user, string $ip): bool` | 四种可见性判定 |
| 是否已投 | `hasVoted(PollingActivity $activity, ?User $user, string $ip): bool` | 登录按用户、游客按 IP |
| 导出 CSV | `exportStatsCsv(PollingActivity $activity): StreamedResponse` | 流式下载响应（`text/csv` 附件 + UTF-8 BOM 头，分块写出、内存与活动规模解耦），匿名活动自动脱敏；类型为 `Symfony\Component\HttpFoundation\StreamedResponse`，控制器直接 return 即可触发浏览器下载 |

`getStats()` 返回结构：

```php
[
    'type' => 'single|multiple|ranking',
    'total_records' => 6,   // 记录总条数
    'total_votes' => 2,     // 参与人次（按 IP|用户|批次 去重）
    'options' => [
        [
            'id' => 1, 'title' => '选项甲', 'description' => '...', 'image' => '...', 'sort' => 1,
            'count' => 2,      // 被选次数
            'percent' => 66.67, // 非 ranking：占比
            // ranking 类型额外字段：
            'score' => 4,      // Borda 总得分
            'rank_distribution' => [1 => 2, 2 => 0], // 各名次被选次数
        ],
        // ...
    ],
]
```

### 3.2 ActivityService（活动管理）

```php
use App\Apps\CmsproPolling\Services\ActivityService;

$activityService = app(ActivityService::class);
```

| 方法 | 签名 | 说明 |
|------|------|------|
| 后台列表 | `getList(array $params): array` | keyword / status / type / start_date / end_date / per_page |
| 用户列表 | `getUserOwnList(int $userId, array $params): array` | 限定 user_id |
| 详情 | `show(int $id): array` | 含选项 |
| 创建 | `store(array $data, int $userId = 0): array` | `userId=0` 表示后台管理员创建 |
| 更新 | `update(int $id, array $data, ?int $limitUserId = null): array` | `limitUserId` 非 null 时仅可操作本人活动（越权返回 40301）；已有记录的活动禁止换类型 |
| 状态流转 | `updateStatus(int $id, int $status, ?int $limitUserId = null): array` | 草稿/进行中/已结束 |
| 删除 | `destroy(int $id, ?int $limitUserId = null): array` | 级联删除选项与记录 |
| 记录列表 | `getRecordList(array $params): array` | activity_id / option_id / user_id / ip / 日期 |
| 格式化 | `formatActivity(PollingActivity $activity, bool $withOptions = false): array` | 追加 effective_status / type_text / is_votable 等展示字段 |

## 四、模型 API

```php
use App\Apps\CmsproPolling\Models\PollingActivity;
use App\Apps\CmsproPolling\Models\PollingOption;
use App\Apps\CmsproPolling\Models\PollingRecord;
```

**PollingActivity 关键能力**：

| 成员 | 说明 |
|------|------|
| 常量 `TYPE_*` / `STATUS_*` / `VISIBILITY_*` | 类型、状态、可见性枚举值 |
| `getEffectiveStatusAttribute()` | 运行时有效状态（截止自动视为已结束，不写库） |
| `isVotable()` | 当前时刻是否可投票 |
| `isRanking()` | 是否排序投票 |
| 关联 `options()` / `records()` / `creator()` | 选项按 sort 排序 |

**直接构造数据示例**（队列/命令行场景）：

```php
$activity = PollingActivity::where('status', PollingActivity::STATUS_ACTIVE)->first();
$result = app(VoteService::class)->submitVote(
    $activity,
    $optionIds,      // 选项 ID 数组，ranking 按名次升序
    $rankMap,        // ranking 显式名次映射，可传 []
    $user,           // User 或 null（游客）
    $ip
);
if ($result['code'] === 0) {
    // 成功，$result['data']['vote_no'] 为批次号
}
```

## 五、二次开发指引

### 5.1 新增投票类型

1. `PollingActivity` 增加 `TYPE_XXX` 常量与 `casts`；
2. `ActivityService::TYPE_MAP` 注册中文名；
3. `VoteService::validateByType()` 的 `match` 增加分支；
4. `VoteService::getStats()` 按需增加计分逻辑；
5. 表单视图增加类型选项，`views` 的模板分支同步；
6. 补充对应测试（`Tests/Feature/VoteServiceTest.php`）。

### 5.2 数据库变更规范

- 新增迁移文件置于 `Migrations/`，命名 `YYYY_MM_DD_HHMMSS_描述.php`；
- `up()` 用 `Schema::hasTable/hasColumn` 保证幂等，`down()` 可完整回滚；
- 表注释为 MySQL 专有语法，必须包裹 `if (DB::connection()->getDriverName() === 'mysql')`（SQLite 测试内存库不支持）；
- 索引用 Laravel 自动生成名（含表名前缀），**禁止自定义短名**（SQLite 索引名为全局命名空间，短名会与系统表冲突）；
- 字段与表必须写 `comment()`。

### 5.3 测试规范

- 测试放 `app/Apps/CmsproPolling/Tests/`，继承 `PollingTestCase`（已内置内存库安全护栏、应用迁移补跑、数据构造助手）；
- 运行：`php artisan test app/Apps/CmsproPolling/Tests`（只用 `php artisan test`，严禁 `phpunit --no-configuration`）;
- 涉及应用路由的用例无需额外配置——基类 `setUp()` 已手动注册 `ServiceProvider`（内存库中 apps 表为空，应用不会自动注册）。

### 5.4 升级兼容注意

- 不要改既有表字段语义，只做增量迁移；
- `polling.activity.stats` 的既有键结构是公开契约，只增不改；
- `vote_no` 批次号语义（同批次多条记录同号、事务内递增）被统计与导出依赖，勿改。

## 六、相关文档

- 《CmsPro-投票系统-特性清单.md》：技术特性全量索引
- 《CmsPro-投票系统-使用指南.md》：安装配置与日常使用
