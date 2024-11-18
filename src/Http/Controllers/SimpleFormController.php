<?php

namespace Ugly\ApiUtils\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Ugly\ApiUtils\Contracts\SimpleForm;
use Ugly\ApiUtils\Traits\ApiResponse;

class SimpleFormController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, string $scene): JsonResponse
    {
        // 获取路由名
        $form = data_get(
            collect(config('ugly.simple_form', []))->where('name', $request->route()->getName())->first(),
            'form.'.$scene
        );

        if (! ($form && isset(class_implements($form)[SimpleForm::class]))) {
            abort(Response::HTTP_NOT_FOUND);
        }

        /** @var SimpleForm $formInstance */
        $formInstance = new $form;

        if ($request->isMethod('post')) {
            DB::transaction(fn () => $formInstance->handle($formInstance->policy($request)));
        }

        return $this->success($formInstance->default());
    }
}
