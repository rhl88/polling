<?php

namespace App\Apps\CmsproPolling\Models;

use App\Models\BaseModel;

class PollingOption extends BaseModel
{
    protected $table = 'app_cmspro_polling_options';

    public const CREATED_AT = 'create_time';

    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'activity_id',
        'title',
        'description',
        'image',
        'sort',
    ];

    protected $casts = [
        'activity_id' => 'integer',
        'title' => 'string',
        'description' => 'string',
        'image' => 'string',
        'sort' => 'integer',
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
     * 关联投票记录
     */
    public function records()
    {
        return $this->hasMany(PollingRecord::class, 'option_id');
    }
}
