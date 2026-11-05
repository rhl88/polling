<?php

namespace App\Apps\CmsproPolling\Models;

use App\Models\BaseModel;
use App\Models\User;

class PollingRecord extends BaseModel
{
    protected $table = 'app_cmspro_polling_records';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'activity_id',
        'option_id',
        'user_id',
        'vote_no',
        'rank',
        'ip',
    ];

    protected $casts = [
        'activity_id' => 'integer',
        'option_id' => 'integer',
        'user_id' => 'integer',
        'vote_no' => 'integer',
        'rank' => 'integer',
        'ip' => 'string',
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联所属活动
     */
    public function activity()
    {
        return $this->belongsTo(PollingActivity::class, 'activity_id');
    }

    /**
     * 关联选项
     */
    public function option()
    {
        return $this->belongsTo(PollingOption::class, 'option_id');
    }

    /**
     * 关联投票用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
