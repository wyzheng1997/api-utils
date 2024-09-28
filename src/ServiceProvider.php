<?php

namespace Ugly\ApiUtils;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Ugly\ApiUtils\Http\Controllers\SimpleFormController;
use Ugly\ApiUtils\Supports\ConfigFluent;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        // 设置默认配置项
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'ugly');

        // 注册 ugly.sys_config
        $this->app->singleton('ugly.sys_config', ConfigFluent::class);

        // 注册 ugly.simple_form 路由
        $this->registerSimpleFormRoutes();
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

    private function registerSimpleFormRoutes(): void
    {
        $simpleForm = config('ugly.simple_form', []);
        foreach ($simpleForm as $form) {
            $path = trim($form['path'], '/').'/{scene}';
            Route::prefix('api')
                ->middleware(array_merge($form['middleware'] ?? [], ['api']))
                ->name($form['name'])
                ->match(['get', 'post'], $path, SimpleFormController::class);
        }
    }
}
