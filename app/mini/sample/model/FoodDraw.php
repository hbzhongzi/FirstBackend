<?php
declare(strict_types=1);

namespace app\mini\sample\model;

use think\Model;

final class FoodDraw extends Model
{
    protected $name = 'mini_food_draw';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = false;
    protected $type = [
        'id'         => 'integer',
        'program_id' => 'integer',
        'user_id'    => 'integer',
        'food_id'    => 'integer',
    ];
}
