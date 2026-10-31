<?php
declare(strict_types=1);

namespace app\common\support;

use app\common\exception\BusinessException;
use app\common\model\MiniProgram;
use think\Request;

final class MiniProgramContext
{
    public static function fromRequest(Request $request): array
    {
        $context = $request->middleware('mini_program');
        if (!is_array($context) || empty($context['key'])) {
            throw new BusinessException('小程序上下文不存在', 40401, 404);
        }

        return $context;
    }

    public static function key(Request $request): string
    {
        return (string) self::fromRequest($request)['key'];
    }

    public static function record(Request $request): MiniProgram
    {
        $program = MiniProgram::where('program_key', self::key($request))
            ->where('status', 1)
            ->find();

        if (!$program) {
            throw new BusinessException('小程序未注册或已停用', 40401, 404);
        }

        return $program;
    }

    public static function recordByAppid(Request $request, string $appid): MiniProgram
    {
        $appid = trim($appid);
        if ($appid === '') {
            throw new BusinessException('小程序 AppID 不能为空', 42200, 422);
        }

        $program = MiniProgram::where('appid', $appid)
            ->where('status', 1)
            ->find();

        if (!$program) {
            throw new BusinessException('小程序 AppID 未注册或已停用', 40401, 404);
        }

        if ($program->program_key !== self::key($request)) {
            throw new BusinessException('小程序 AppID 与接口项目不匹配', 42200, 422);
        }

        return $program;
    }
}
