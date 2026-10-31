<?php
declare(strict_types=1);

use app\middleware\Authenticate;
use app\middleware\ResolveMiniProgram;
use app\mini\sample\controller\v1\Food;
use think\facade\Route;

// sample 项目的业务路由。公共接口已由 route/app.php 统一生成。
$middleware = [[ResolveMiniProgram::class, ['sample']], [Authenticate::class, []]];

Route::get('api/sample/v1/foods', [Food::class, 'foods'])->middleware($middleware);
Route::get('api/sample/v1/foods/<foodId>', [Food::class, 'detail'])->middleware($middleware);
Route::get('api/sample/v1/food-pool', [Food::class, 'pool'])->middleware($middleware);
Route::put('api/sample/v1/food-pool', [Food::class, 'updatePool'])->middleware($middleware);
Route::post('api/sample/v1/food-draws', [Food::class, 'draw'])->middleware($middleware);
Route::get('api/sample/v1/food-draws', [Food::class, 'draws'])->middleware($middleware);
Route::get('api/sample/v1/food-book', [Food::class, 'book'])->middleware($middleware);
Route::get('api/sample/v1/food-book/<foodId>', [Food::class, 'bookDetail'])->middleware($middleware);
Route::get('api/sample/v1/food-stats', [Food::class, 'stats'])->middleware($middleware);
Route::post('api/sample/v1/feedback', [Food::class, 'feedback'])->middleware($middleware);
