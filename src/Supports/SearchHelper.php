<?php

namespace Ugly\ApiUtils\Supports;

use Illuminate\Database\Eloquent\Builder;

class SearchHelper
{
    /**
     * 从请求中解析查询参数值.
     */
    private static function decodeInput(bool $isWhereHas, string $field, $operator): mixed
    {
        // whereHas 默认key PS: 'admin.name' 转换成 'admin_name'
        if ($isWhereHas) {
            $field = str_replace('.', '_', $field);
        }

        // 自定义 key 优先级最高
        $field = is_array($operator) ? $operator[1] : $field;

        // 获取请求参数
        return request()->input($field);
    }

    /**
     * 解析方法名与参数
     */
    private static function decodeMethodNameAndArgs(string|array $operator, array|string $input): array
    {
        $operator = is_array($operator) ? $operator[0] : $operator;
        switch ($operator) {
            case 'in':
            case 'between':
                $method = 'where'.ucfirst($operator);
                $args = [is_array($input) ? $input : explode(',', $input)];
                break;
            case 'like':
                $method = 'where';
                $args = ['like', '%'.$input.'%'];
                break;
            default:
                if (Builder::hasGlobalMacro($operator)) { // 优先调用全局宏
                    $method = $operator;
                    $args = [$input];
                } else {
                    $method = 'where';
                    $args = [$operator, $input];
                }
                break;
        }

        return [$method, $args];
    }

    /**
     * 构建查询条件闭包.
     */
    public static function buildWhereClause(array $options = []): \Closure
    {
        return function (Builder $query) use ($options) {
            foreach ($options as $field => $operator) {
                $fieldInfo = pathinfo($field);
                $isWhereHas = isset($fieldInfo['extension']);

                // 解析输入
                $input = self::decodeInput($isWhereHas, $field, $operator);

                // 跳过空值
                if (blank($input)) {
                    continue;
                }

                // 自定义查询优先级最高
                if ($operator instanceof \Closure) {
                    $query->where(fn ($q) => call_user_func($operator, $q, $input));

                    continue;
                }

                // 解析 方法名与参数
                [$method, $args] = self::decodeMethodNameAndArgs($operator, $input);
                // 拼接查询
                if ($isWhereHas) {
                    $query->whereHas($fieldInfo['filename'], fn ($q) => $q->$method($fieldInfo['extension'], ...$args));
                } else {
                    $query->$method($fieldInfo['filename'], ...$args);
                }
            }
        };
    }

    /**
     * 构建排序.
     */
    public static function buildOrderBy(Builder $builder, array $sort): Builder
    {
        $sort_by = (string) request('sort_by');
        if (blank($sort_by)) {
            return $builder;
        }

        //请求参数格式：sort_by=asc(last_modified),desc(email)
        preg_match_all('/(asc|desc)\((.*?)\)/', $sort_by, $matches);
        foreach ($matches[0] as $index => $match) {
            $direction = $matches[1][$index];
            $field = $matches[2][$index];
            if (in_array($field, $sort)) {
                $builder->orderBy($field, $direction);
            } elseif (data_get($sort, $field) instanceof \Closure) {
                call_user_func($sort[$field], $builder, $direction);
            }
        }

        return $builder;
    }
}
