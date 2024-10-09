<?php

namespace Ugly\ApiUtils\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Ugly\ApiUtils\Supports\SearchHelper;

/**
 * @mixin Model
 */
trait SearchModel
{
    /**
     * @param  array  $search  查询配置
     *                         1、['name' => 'like'] PS: name like '%xxx%'
     *                         2、['category_id' => ['=', 'cate_id']]   PS: category_id = request('cate_id')
     *                         3、['type' => fn($query, $input) => $query->where('type', $input)] PS: 自定义查询
     *                         5、['admin.name' => 'like'] PS: whereHas('admin', fn($q) => $->where('name', 'like', '%xxx%')) PS: 关联查询
     * @param  array  $sort  排序配置, 支持闭包
     *                       1、['name']
     *                       2、['price' => fn($query, $direction) => $query->orderBy('total_price', $direction)]
     * @return Builder 查询构造器
     */
    public static function search(array $search = [], array $sort = []): Builder
    {
        // 处理查询逻辑.
        $builder = self::query()->where(SearchHelper::buildWhereClause($search));

        // 处理排序逻辑
        return SearchHelper::buildOrderBy($builder, $sort);
    }
}
