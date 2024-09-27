<?php

namespace Ugly\ApiUtils;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Ugly\ApiUtils\Supports\ConfigFluent;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // 设置默认配置项
        if (empty(config('ugly.sys_config'))) {
            config('ugly.sys_config', include '../config/config.php');
        }

        // 注册 ugly.sys_config
        $this->app->singleton('ugly.sys_config', ConfigFluent::class);

    }

    public function boot(): void
    {
        if ($this->app->isLocal() && $this->app->runningInConsole()) {
            // 发布迁移文件。
            $this->publishes([
                __DIR__.'/../database/migrations/create_sys_configs_table.php' => database_path('migrations/'.date('Y_m_d_His').'_create_sys_configs_table.php'),
            ]);
        }
    }
}
