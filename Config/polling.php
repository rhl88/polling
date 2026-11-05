<?php

return [
    // 默认每人限投次数（新建活动预填值，可被活动级配置覆盖）
    'default_votes_per_user' => 1,

    // 同一IP在同一活动下最多可提交投票的次数，0 为不限制
    'ip_limit' => 0,

    // 是否允许用户端发起投票
    'allow_user_create' => true,
];
