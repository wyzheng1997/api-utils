<?php

namespace Ugly\ApiUtils\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ConfigModel extends Model
{
    public $incrementing = false;

    protected $fillable = ['slug', 'value', 'remark'];

    protected $primaryKey = 'slug';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('ugly.sys_config.table') ?? parent::getTable();
    }

    /**
     * 修改器。
     */
    public function value(): Attribute
    {
        return new Attribute(
            get: function ($value) {
                return match (true) {
                    json_validate($value) => json_decode($value, true),
                    is_numeric($value) => str_contains($value, '.') ? (float) $value : (int) $value,
                    default => $value,
                };
            },
            set: function ($value) {
                return match (true) {
                    is_string($value), is_numeric($value) => $value,
                    is_array($value) => json_encode($value),
                    default => '',
                };
            }
        );
    }
}
