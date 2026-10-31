<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\exception\BusinessException;
use app\common\model\MiniProgram;
use app\common\support\Crypto;
use EasyWeChat\MiniApp\Application;
use Throwable;

final class WechatService
{
    public function codeLogin(MiniProgram $program, string $code): array
    {
        $appid = trim((string) $program->appid);
        $secretCiphertext = trim((string) ($program->secret_ciphertext ?? ''));

        if ($appid === '' || $secretCiphertext === '') {
            throw new BusinessException('微信登录配置未完成', 50000, 500);
        }

        try {
            $secret = trim(Crypto::decrypt($secretCiphertext));
        } catch (Throwable) {
            // 密文格式错误、APP_KEY 变更或密文无法解密都属于部署配置错误。
            throw new BusinessException('微信登录配置未完成', 50000, 500);
        }

        if ($secret === '') {
            throw new BusinessException('微信登录配置未完成', 50000, 500);
        }

        try {
            // EasyWeChat 负责微信接口请求和响应解析；AppID、AppSecret 来自数据库记录。
            $application = new Application([
                'app_id' => $appid,
                'secret' => $secret,
                'http'   => [
                    'timeout' => 5,
                ],
            ]);
            $payload = $application->getUtils()->codeToSession($code);
        } catch (Throwable) {
            // 不把微信 SDK 的原始异常、AppSecret 或上游响应内容返回给客户端。
            throw new BusinessException('微信登录服务暂不可用，请稍后重试', 50300, 503);
        }

        if (!is_array($payload) || !empty($payload['errcode']) || empty($payload['openid'])) {
            throw new BusinessException('微信登录失败，请稍后重试', 50300, 503);
        }

        return [
            'openid'  => (string) $payload['openid'],
            'unionid' => isset($payload['unionid']) ? (string) $payload['unionid'] : null,
        ];
    }
}
