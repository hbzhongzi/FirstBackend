<?php
declare(strict_types=1);

namespace app\mini\sample\model;

use think\Model;

final class Feedback extends Model
{
    protected $name = 'mini_feedback';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $type = [
        'id'         => 'integer',
        'program_id' => 'integer',
        'user_id'    => 'integer',
        'handled_by' => 'integer',
    ];
}
