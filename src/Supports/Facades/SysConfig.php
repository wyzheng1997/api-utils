<?php

namespace Ugly\ApiUtils\Supports\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static get(string|null $key = null, mixed $default = null): mixed
 * @method static set(array|string $key, mixed $value = null): void
 * @method static forgetCache(): void
 *
 * @see \Ugly\ApiUtils\Supports\ConfigFluent
 */
class SysConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ugly.sys_config';
    }
}
