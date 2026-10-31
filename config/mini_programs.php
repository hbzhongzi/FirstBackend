<?php
declare(strict_types=1);

/**
 * 小程序/业务租户路由白名单。公开 AppID 和加密 AppSecret 统一从 sys_mini_program 读取。
 */
return [
    'programs' => [
        // 示例项目。复制此结构注册真实小程序，并为其创建独立业务目录和路由文件。
        'sample' => [
            'name'          => '示例小程序',
            'enabled'       => true,
            'features'      => [],
        ],
    ],
    'upload' => [
        'image_max_bytes'    => 10485760,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    ],
];
