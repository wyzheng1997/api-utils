<?php

namespace Ugly\ApiUtils\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 树状模型
 *
 * @mixin Model
 */
trait HasTree
{
    protected string $pidKey = 'pid';

    protected string $treeLevelKey = 'tree_level';

    protected string $treePathKey = 'tree_path';

    /**
     * 获取上级字段名称.
     */
    public function getPidKey(): string
    {
        return $this->pidKey;
    }

    /**
     * 获取层级字段名称.
     */
    public function getTreeLevelKey(): string
    {
        return $this->treeLevelKey;
    }

    /**
     * 获取层级路径字段名称.
     */
    public function getTreePathKey(): string
    {
        return $this->treePathKey;
    }

    protected static function booted(): void
    {
        // 保存前检查上级合法性
        static::saving(function (Model $model) {
            $pidKey = $model->getPidKey();
            $pid = (int) $model->getAttribute($pidKey);
            $id = $model->getKey();

            // 检查上级是否存在
            if (is_null($id) && $pid > 0 && ! static::query()->where('id', $pid)->exists()) {
                abort(400, '所选上级不存在');
            }

            // 检查是否选择了自己或下级作为上级
            if (
                $id && $model->isDirty($pidKey) &&
                ($pid === $id || $model->allChildren()->where('id', $pid)->exists())
            ) {
                abort(400, '不能选择自己以及下级作为上级');
            }

        });

        // 保存后更新层级和索引
        static::saved(function (Model $model) {
            // 保存后更新层级和索引
            $pidKey = $model->getPidKey();
            $levelKey = $model->getTreeLevelKey();
            $pathKey = $model->getTreePathKey();

            if ($model->getAttribute($pathKey) && ! $model->isDirty($pidKey)) {
                return;
            }

            $pid = (int) $model->getAttribute($pidKey);
            $updateData = [$levelKey => 1, $pathKey => $model->getKey()];

            // 如果有父级，则通过父级来计算层级和索引
            if ($pid > 0) {
                $parent = static::query()->findOrFail($pid);
                $updateData[$levelKey] = (int) $parent->getAttribute($levelKey) + 1;
                $updateData[$pathKey] = $parent->getAttribute($pathKey).'-'.$model->getKey();
            }
            static::query()->where('id', $model->getKey())->update($updateData);
        });
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, $this->getPidKey(), $this->getKeyName());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, $this->getPidKey(), $this->getKeyName());
    }

    /**
     * 用于获取当前模型的所有子项
     *
     * @return Builder 返回一个查询构建器实例，该实例包含了所有满足条件的子项记录
     */
    public function allChildren(): Builder
    {
        $pathKey = $this->getTreePathKey();

        return static::query()->where($pathKey, 'like', $this->getAttribute($pathKey).'-%');
    }

    /**
     * 用于获取当前模型的所有父级
     *
     * @return Builder 返回一个查询构建器实例，该实例包含了所有满足条件的父级记录
     */
    public function allParent(): Builder
    {
        $pathKey = $this->getTreePathKey();

        return static::query()->where($pathKey, 'like', '%-'.$this->getAttribute($pathKey));
    }
}
