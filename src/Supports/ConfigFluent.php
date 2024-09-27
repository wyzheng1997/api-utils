<?php

namespace Ugly\ApiUtils\Supports;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;
use Ugly\ApiUtils\Models\ConfigModel;

class ConfigFluent extends Fluent
{
    /** 缓存驱动 @var string $cacheDriver */
    private string $cacheDriver;

    /** 缓存键名 @var string $cacheKey */
    private string $cacheKey;

    /** 缓存过期时间 @var \DateTimeInterface|\DateInterval|int $cacheTTL */
    private \DateTimeInterface|\DateInterval|int $cacheTTL;

    public function __construct($attributes = [])
    {
        $this->cacheDriver = $this->getCacheDriverFromConfig();
        $this->cacheKey = config('ugly.sys_config.cache.key');
        $this->cacheTTL = config('ugly.sys_config.cache.ttl');
        parent::__construct($attributes);
    }

    /* 获取所有系统配置 */
    private function getSysConfigs(): array
    {
        return Cache::driver($this->cacheDriver)->remember($this->cacheKey, $this->cacheTTL, function () {
            return ConfigModel::query()->get(['value', 'slug'])->pluck('value', 'slug')->toArray();
        });
    }

    /* 获取缓存驱动 */
    private function getCacheDriverFromConfig(): string
    {
        // 从配置文件中获取
        $cacheDriver = config('ugly.sys_config.cache.store');
        if (! $cacheDriver) {
            $cacheDriver = config('cache.default');
        }
        if (! array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array'; // 默认使用 array 缓存
        }

        return $cacheDriver;
    }

    /* 清除缓存 */
    public function forgetCache(): void
    {
        $this->attributes = [];
        Cache::driver($this->cacheDriver)->forget($this->cacheKey);
    }

    /* 获取配置 */
    public function get($key = null, $default = null): mixed
    {
        if (empty($this->attributes)) {
            $this->attributes = $this->getSysConfigs();
        }

        return match (true) {
            is_null($key) => $this->attributes,
            is_string($key) => parent::get($key, $default)
        };
    }

    /* 更新/新增配置 */
    public function set(array|string $key, mixed $value = null): static
    {
        $data = is_array($key) ? $key : [$key => $value];
        foreach ($data as $slug => $val) {
            if (array_key_exists($slug, $this->attributes)) {
                ConfigModel::query()->where('slug', $slug)->update(['value' => $val]);
            } else {
                ConfigModel::query()->create(['slug' => $slug, 'value' => $val]);
            }
        }

        // 清除缓存
        $this->forgetCache();

        return $this;
    }
}
