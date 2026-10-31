<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('CACHE_DRIVER', 'redis'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        'redis' => [
            // Redis 驱动支持 PHP ext-redis 或 predis/predis
            'type'       => 'Redis',
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_DB', 0),
            'timeout'    => (float) env('REDIS_TIMEOUT', 2),
            'persistent' => env('REDIS_PERSISTENT', false),
            // ThinkPHP 8 当前 Redis 驱动与 Predis 同时处理 prefix 会重复加前缀。
            // 使用 ext-redis 时保留命名空间；Predis 下交给空前缀避免写入后读不到。
            'prefix'     => extension_loaded('redis') ? env('REDIS_PREFIX', 'mytotal:') : '',
            'expire'     => (int) env('CACHE_EXPIRE', 0),
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
    ],
];
