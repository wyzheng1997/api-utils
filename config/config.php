<?php

return [
    /*
     | ------------------------------------------------
     | 对于 sys_config 的配置。
     | ------------------------------------------------
     */
    'sys_config' => [
        // 表名称
        'table' => 'sys_configs',

        // 缓存配置
        'cache' => [
            /*
             | 设置用于 sys_config 的特定缓存驱动程序。
             | 默认为 default。相当于 cache.php 配置文件中的 default。
             | 需要在 cache.php 配置文件中的 stores 数组中定义驱动程序。
             | 当匹配不到驱动程序时候，将使用 array 驱动。
             */
            'store' => 'default',

            // 缓存键
            'key' => 'ugly-api-utils.sys-config.cache',

            /*
             | 默认缓存 24 小时。
             | 当表数据发生变化时，将自动更新缓存。
             */
            'ttl' => \DateInterval::createFromDateString('24 hours'),
        ],
    ],

    /*
     | ------------------------------------------------
     | 简单表单配置。
     | ------------------------------------------------
     | 二维数组，单项配置项如下：
     |
     | [
     |   'middleware' => ['api'], // 指定路由中间件。
     |   'path' => '/admin/config', // 表示注册支持GET和POST的 /api/admin/config/{scene} 路由。
     |   'name' => 'admin.config', // 路由名称
     |   'form' => [
     |       // 表单配置。key 对应路由的{scene}变量。
     |       'key' => \App\Forms\TestConfigFrom::class
     |   ],
     | ]
     */
    'simple_form' => [],
];
