<?php

namespace Ugly\ApiUtils\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Ugly\ApiUtils\Services\FormService;
use Ugly\ApiUtils\Traits\ApiResponse;

abstract class ApiResourceController extends Controller
{
    use ApiResponse;

    /**
     * 抽象表单配置类
     */
    abstract protected function form(): FormService;

    /**
     * 保存.
     *
     * @throws \Throwable
     */
    public function store(): JsonResponse
    {
        $model = DB::transaction(fn () => $this->form()->setScene(FormService::CREATE)->save());

        return $this->success([
            $model->getKeyName() => $model->getKey(),
        ], Response::HTTP_CREATED);
    }

    /**
     * 更新.
     *
     * @throws \Throwable
     */
    public function update($id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            $form = $this->form();
            $ids = explode(',', $id);
            abort_if(count($ids) > 100, Response::HTTP_BAD_REQUEST, '批量操作不能超过100条');
            foreach ($ids as $key) {
                $form->setKey($key)->setScene(FormService::UPDATE)->save();
            }
        });

        return $this->success();
    }

    /**
     * 删除.
     *
     * @throws \Throwable
     */
    public function destroy($id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            $form = $this->form();
            $ids = explode(',', $id);
            abort_if(count($ids) > 100, Response::HTTP_BAD_REQUEST, '批量删除不能超过100条！');
            foreach ($ids as $key) {
                $form->setKey($key)->setScene(FormService::DELETE)->delete();
            }
        });

        return $this->success();
    }
}
