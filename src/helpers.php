<?php

use Illuminate\Database\Schema\Blueprint;

if (! function_exists('arr2tree')) {
    /**
     * 数组/集合 转换成树级结构。
     */
    function arr2tree(array $list, ?\Closure $transform = null, string $id = 'id', string $pid = 'pid', string $children = 'children'): array
    {
        [$map, $tree] = [[], []];
        foreach ($list as $item) {
            $map[data_get($item, $id)] = $transform ? call_user_func($transform, $item) : $item;
        }

        foreach ($list as $item) {
            if (isset($item[$pid]) && isset($map[$item[$pid]])) {
                $map[$item[$pid]][$children][] = &$map[$item[$id]];
            } else {
                $tree[] = &$map[$item[$id]];
            }
        }
        unset($map);

        return $tree;
    }
}

if (! function_exists('generate_tree_migrate')) {
    /**
     * 树形结构迁移.
     */
    function generate_tree_migrate(Blueprint $table, string $pid = 'pid', string $treeLevel = 'tree_level', string $treePath = 'tree_path'): void
    {
        $table->unsignedBigInteger($pid)->default(0)->comment('上级');
        $table->unsignedTinyInteger($treeLevel)->default(1)->comment('树层级');
        $table->string($treePath)->index()->default('')->comment('树索引路径');
    }
}
