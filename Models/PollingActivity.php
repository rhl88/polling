<?php

namespace App\Apps\CmsproPolling\Models;

use App\Models\BaseModel;
use App\Models\User;

class PollingActivity extends BaseModel
{
    /** 投票类型：单选 */
    public const TYPE_SINGLE = 'single';

    /** 投票类型：多选 */
    public const TYPE_MULTIPLE = 'multiple';

    /** 投票类型：排序 */
    public const TYPE_RANKING = 'ranking';

    /** 状态：草稿 */
    public const STATUS_DRAFT = 0;

    /** 状态：进行中 */
    public const STATUS_ACTIVE = 1;

    /** 状态：已结束 */
    public const STATUS_ENDED = 2;

    /** 结果可见性：实时可见 */
    public const VISIBILITY_ALWAYS = 'always';

    /** 结果可见性：投票后可见 */
    public const VISIBILITY_AFTER_VOTE = 'after_vote';

    /** 结果可见性：结束后可见 */
    public const VISIBILITY_END = 'end';

    /** 结果可见性：不公开 */
    public const VISIBILITY_NEVER = 'never';

    protected $table = 'app_cmspro_polling_activities';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'title',
        'description',
        'type',
        'max_choices',
        'start_time',
        'end_time',
        'status',
        'require_login',
        'votes_per_user',
        'ip_limit',
        'anonymous',
        'result_visibility',
        'user_id',
        'sort',
    ];

    protected $casts = [
        'title' => 'string',
        'description' => 'string',
        'type' => 'string',
        'max_choices' => 'integer',
        'start_time' => 'datetime:Y-m-d H:i:s',
        'end_time' => 'datetime:Y-m-d H:i:s',
        'status' => 'integer',
        'require_login' => 'integer',
        'votes_per_user' => 'integer',
        'ip_limit' => 'integer',
        'anonymous' => 'integer',
        'result_visibility' => 'string',
        'user_id' => 'integer',
        'sort' => 'integer',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联投票选项
     */
    public function options()
    {
        return $this->hasMany(PollingOption::class, 'activity_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * 关联投票记录
     */
    public function records()
    {
        return $this->hasMany(PollingRecord::class, 'activity_id');
    }

    /**
     * 关联创建者
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取运行时有效状态（投票截止后自动视为已结束，无需写库）
     */
    public function getEffectiveStatusAttribute(): int
    {
        if ((int) $this->status === self::STATUS_ACTIVE
            && $this->end_time !== null
            && now()->gt($this->end_time)
        ) {
            return self::STATUS_ENDED;
        }

        return (int) $this->status;
    }

    /**
     * 是否允许投票（运行时判定）
     */
    public function isVotable(): bool
    {
        return $this->effective_status === self::STATUS_ACTIVE
            && ($this->start_time === null || !now()->lt($this->start_time))
            && ($this->end_time === null || !now()->gt($this->end_time));
    }

    /**
     * 类型是否为排序投票
     */
    public function isRanking(): bool
    {
        return $this->type === self::TYPE_RANKING;
    }
}
