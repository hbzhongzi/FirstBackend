<?php
declare(strict_types=1);

namespace app\mini\sample\model;

use think\Model;

final class Food extends Model
{
    protected $name = 'mini_food';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $type = [
        'id'         => 'integer',
        'program_id' => 'integer',
        'status'     => 'integer',
        'sort'       => 'integer',
        'steps'      => 'json',
    ];
}
