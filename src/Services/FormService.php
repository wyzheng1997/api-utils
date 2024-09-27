<?php

namespace Ugly\ApiUtils\Services;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static auth(array|\Closure $closure)
 * @method static validate(array|\Closure $rules)
 * @method static prepareInput(array|\Closure $closure)
 * @method static saving(array|\Closure $closure)
 * @method static saved(array|\Closure $closure)
 * @method static deleting(array|\Closure $closure)
 * @method static deleted(array|\Closure $closure)
 */
class FormService
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    /**
     * 当前表单场景。
     */
    protected string $scene;

    /**
     * 需要处理的模型.
     */
    private mixed $model;

    /**
     * 操作model的主键值.
     */
    private int|string $key;

    /**
     * form hook函数.
     */
    private array $hooks = [
        'validate' => [], // 表单验证
        'auth' => true, // 权限认证
        'prepareInput' => null, // 预处理输入数据
        'saving' => null, // 保存前回调
        'saved' => null, // 保存后回调
        'deleting' => null, // 删除前回调
        'deleted' => null, // 删除后回调
    ];

    /**
     * 允许行内编辑的字段.
     */
    private array $allowInlineUpdateFields = [];

    /**
     * 保存通过表单验证后的数据.
     */
    public array $safeData = [];

    /**
     * 构造函数.
     */
    private function __construct(string|BuilderContract $model)
    {
        $this->model = is_string($model) ? app($model) : $model;
    }

    /**
     * 创建实例.
     */
    public static function make($model): static
    {
        return new static($model);
    }

    /**
     * 获取模型.
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * 设置主键（编辑/删除）.
     *
     * @return $this
     */
    public function setKey(int|string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * 获取模型ID.
     */
    public function getKey(): string|int
    {
        return $this->key ?? $this->model->getKey();
    }

    /**
     * 设置场景.
     */
    public function setScene(string $scene): static
    {
        $this->scene = $scene;

        return $this;
    }

    /**
     * 是否是创建.
     */
    public function isCreate(): bool
    {
        return $this->scene === self::CREATE;
    }

    /**
     * 是否是更新.
     */
    public function isUpdate(): bool
    {
        return $this->scene === self::UPDATE;
    }

    /**
     * 是否是行内编辑.
     */
    public function isInlineUpdate(): bool
    {
        // 约定PATCH请求为行内编辑的请求
        return $this->isUpdate() && request()->isMethod('PATCH');
    }

    /**
     * 是否是删除.
     */
    public function isDelete(): bool
    {
        return $this->scene === self::DELETE;
    }

    /**
     * 表单唯一验证规则.
     */
    public function unique(?string $table = null, $column = 'NULL'): Unique
    {
        $rule = Rule::unique($table ?: get_class($this->getModel()), $column);
        if ($this->isUpdate()) {
            $rule->ignore($this->getKey());
        }

        return $rule;
    }

    /**
     * 设置允许行内编辑的字段.
     */
    public function inlineUpdate(array $fields): static
    {
        $this->allowInlineUpdateFields = $fields;

        return $this;
    }

    /**
     * 保存表单数据.
     */
    public function save()
    {
        // 请求实例
        $request = request();

        // 编辑模式下先获取需要编辑的资源.
        if ($this->isUpdate()) {
            $this->model = $this->model->findOrFail($this->key);
        }

        // 执行表单验证
        $validateConfig = $this->decodeValidateConfig();
        $ignoreFields = array_shift($validateConfig); // 入库时需要排除的字段
        $this->safeData = $request->validate(...$validateConfig);

        // auth
        if (! $this->callHook('auth')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // 预处理输入
        $this->callHook('prepareInput');

        // 保存前
        $this->callHook('saving');

        // 入库
        $allowData = array_diff_key($this->safeData, array_flip($ignoreFields));
        if ($this->isUpdate()) {
            foreach ($allowData as $key => $val) {
                $this->model->{$key} = $val;
            }
            $this->model->save();
        }
        if ($this->isCreate()) {
            $this->model = $this->model->create($allowData);
        }

        // 入库后
        $this->callHook('saved');

        return $this->model;
    }

    /**
     * 删除数据.
     */
    public function delete()
    {
        // 获取资源
        $this->model = $this->model->findOrFail($this->key);

        // auth
        if (! $this->callHook('auth')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // 删除前
        $this->callHook('deleting');

        // 删除
        $res = $this->model->delete();

        // 删除后
        $this->callHook('deleted');

        return $res;
    }

    /**
     * 处理魔术方法.
     */
    public function __call(string $method, array $parameters)
    {
        if (isset($this->hooks[$method])) {  // 设置hook
            $this->hooks[$method] = $parameters[0];
        }

        return $this;
    }

    /**
     * 执行Hook.
     */
    private function callHook(string $hookName, ...$args): mixed
    {
        $hook = $this->hooks[$hookName];
        if (is_callable($hook)) {
            return call_user_func($hook, $this, ...$args);
        }

        return $hook;
    }

    /**、
     * 解析表单验证配置.
     * @return array|array[]
     * @throws ValidationException
     */
    private function decodeValidateConfig(): array
    {
        // 解析行内编辑字段
        $inlineField = $this->decodeInlineUpdateField();

        // 解析规则
        $results = [[], []];
        $validateConfig = $this->callHook('validate');
        if (empty($validateConfig) || ! is_array($validateConfig)) {
            return $results;
        }

        if (Arr::isAssoc($validateConfig)) { // 只有规则
            $results = [...$this->formatRules($validateConfig)];
        } else {
            $rules = array_shift($validateConfig);
            $results = [...$this->formatRules($rules), ...$validateConfig];
        }

        if ($this->isInlineUpdate()) {
            // 行内编辑只保留特定字段
            $results[1] = array_filter($results[1], function ($key) use ($inlineField) {
                return str_starts_with($key, $inlineField) || str_starts_with($key, "!$inlineField");
            }, ARRAY_FILTER_USE_KEY);
        }

        return $results;
    }

    /**
     * 格式化验证规则.
     */
    private function formatRules(array $validateConfig): array
    {
        $rules = [];
        $ignore = [];
        foreach ($validateConfig as $key => $val) {
            if (str_starts_with($key, '!')) {
                $realKey = substr($key, 1);
                $rules[$realKey] = $val;
                if (! str_contains($key, '.')) {
                    $ignore[] = $realKey;
                }
            } else {
                $rules[$key] = $val;
            }
        }

        return [$rules, $ignore];
    }

    /**
     * 解析行内编辑字段.
     *
     * @throws ValidationException
     */
    private function decodeInlineUpdateField(): string
    {
        if (! $this->isInlineUpdate()) {
            return '';
        }

        $validMessage = [];
        if (! request()->has('field')) {
            $validMessage['field'] = 'field is required';
        }
        if (! request()->has('value')) {
            $validMessage['value'] = 'value is required';
        }
        if (! empty($validMessage)) {
            throw ValidationException::withMessages($validMessage);
        }

        $field = request()->input('field');
        $value = request()->input('value');

        // 检查是否允许行内编辑
        if (! in_array($field, array_keys($this->allowInlineUpdateFields))) {
            ValidationException::withMessages([$field => '不允许修改！']);
        }

        // 合并到请求中
        request()->merge([$field => $value]);

        return $field;
    }
}
