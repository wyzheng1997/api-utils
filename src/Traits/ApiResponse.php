<?php

namespace Ugly\ApiUtils\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * 统一http响应。
 */
trait ApiResponse
{
    /**
     * 成功响应。
     *
     * @example
     *  1、$this->success(); 状态码 200的空响应
     *  2、$this->success(201); 状态码 201的空响应
     *  3、$this->success(['id' => 1]); 状态码 200，携带数据的响应
     *  4、$this->success(['id' => 1], 201); 状态码 201，携带数据的响应
     */
    final public function success(array|int|JsonResource $data = [], int $status = Response::HTTP_OK): JsonResponse
    {
        if (is_int($data)) {
            $status = $data;
            $data = [];
        }

        return response()->json($data, $status);
    }

    /**
     * 失败响应。
     *
     * @param  string  $msg  失败信息
     * @param  int  $code  失败码
     * @param  int|null  $httpCode  http状态码
     */
    final public function failed(string $msg = '操作失败', int $code = Response::HTTP_BAD_REQUEST, ?int $httpCode = null): JsonResponse
    {
        if (is_null($httpCode)) {
            // 默认使用code作为http状态码
            $httpCode = $code;
            if ($httpCode < 100 || $httpCode >= 600) {
                // http不合法时，默认使用400
                $httpCode = Response::HTTP_BAD_REQUEST;
            }
        }

        return response()->json([
            'code' => $code,
            'message' => $msg,
        ], is_null($httpCode) ? $code : $httpCode);
    }

    /**
     * 分页响应。
     *
     * @param  BuilderContract  $query  数据库查询构造器
     * @param  null|\Closure|string  $resource  资源转换类或者闭包
     */
    final public function paginate(BuilderContract $query, string|null|\Closure $resource = null): JsonResponse
    {
        $page = request()->integer('page', 1);
        // 最少1，最多1000，默认15
        $limit = max(min(request()->integer('limit', 15), 1000), 1);
        $total = $query->count();
        $queryData = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json([
            'data' => $this->decodeResource($queryData, $resource),
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'last_page' => $total > 0 ? ceil($total / $limit) : 1,
            ],
        ]);
    }

    /**
     * 解码资源。
     */
    final protected function decodeResource(Collection $data, string|null|\Closure $resource = null): mixed
    {
        return match (true) {
            is_null($resource) => $data,
            $resource instanceof \Closure => $data->transform($resource),
            is_subclass_of($resource, JsonResource::class) => $resource::collection($data),
        };
    }
}
