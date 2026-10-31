<?php
declare(strict_types=1);

namespace app\middleware;

use app\common\exception\BusinessException;
use app\common\support\MiniProgramContext;
use app\common\service\TokenService;
use Closure;
use think\Request;
use think\Response;

/** 校验 access token，并把认证用户写入请求上下文。 */
final class Authenticate
{
    private TokenService $tokens;

    public function __construct(TokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->readToken($request);
        $program = MiniProgramContext::record($request);
        $auth = $this->tokens->resolveAccess($token, (int) $program->id);

        $request->withMiddleware([
            'auth_user'  => $auth['user'],
            'auth_token' => $auth['token'],
        ]);

        return $next($request);
    }

    private function readToken(Request $request): string
    {
        $authorization = $this->readAuthorization($request);
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            return trim($matches[1]);
        }

        // 仅作为旧客户端的过渡兼容，不改变新接口契约。
        $token = trim((string) $request->header('token', ''));
        if ($token === '') {
            throw new BusinessException('未提供登录凭证', 40100, 401);
        }

        return $token;
    }

    /**
     * Apache + mod_fcgid 在重写请求后可能将 Authorization 放到
     * REDIRECT_HTTP_AUTHORIZATION；兼容两种 CGI 变量名称。
     */
    private function readAuthorization(Request $request): string
    {
        $authorization = trim((string) $request->header('authorization', ''));
        if ($authorization !== '') {
            return $authorization;
        }

        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $value = trim((string) $request->server($key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }
}
