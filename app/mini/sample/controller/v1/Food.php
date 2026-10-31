<?php
declare(strict_types=1);

namespace app\mini\sample\controller\v1;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\response\ApiResponse;
use app\mini\sample\service\FoodService;
use think\response\Json;

final class Food extends BaseController
{
    public function foods(): Json
    {
        return $this->success(app()->make(FoodService::class)->listFoods($this->request));
    }

    public function detail(int $foodId): Json
    {
        return $this->success(app()->make(FoodService::class)->detail($this->request, $foodId));
    }

    public function pool(): Json
    {
        return $this->success(app()->make(FoodService::class)->getPool($this->request));
    }

    public function updatePool(): Json
    {
        return $this->success(app()->make(FoodService::class)->updatePool($this->request, $this->body()));
    }

    public function draw(): Json
    {
        return $this->success(app()->make(FoodService::class)->draw($this->request, $this->body()));
    }

    public function draws(): Json
    {
        return $this->success(app()->make(FoodService::class)->draws($this->request));
    }

    public function book(): Json
    {
        return $this->success(app()->make(FoodService::class)->book($this->request));
    }

    public function bookDetail(int $foodId): Json
    {
        return $this->success(app()->make(FoodService::class)->bookDetail($this->request, $foodId));
    }

    public function stats(): Json
    {
        return $this->success(app()->make(FoodService::class)->stats($this->request));
    }

    public function feedback(): Json
    {
        return $this->success(app()->make(FoodService::class)->feedback($this->request, $this->body()));
    }

    private function body(): array
    {
        $body = $this->request->post(false);
        if (!is_array($body)) {
            throw new BusinessException('请求数据格式错误', 40000, 400);
        }
        return $body;
    }
}
