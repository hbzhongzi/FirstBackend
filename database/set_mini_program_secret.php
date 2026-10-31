<?php
declare(strict_types=1);

use app\common\model\MiniProgram;
use app\common\support\Crypto;
use think\App;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
$app->initialize();

$appid = trim((string) ($argv[1] ?? ''));
$secret = (string) ($argv[2] ?? '');
if ($appid === '' || $secret === '') {
    fwrite(STDERR, "用法：php database/set_mini_program_secret.php <appid> <appsecret>\n");
    exit(1);
}

$program = MiniProgram::where('appid', $appid)->find();
if (!$program) {
    fwrite(STDERR, "未找到对应 AppID 的小程序注册记录。\n");
    exit(1);
}

$program->save(['secret_ciphertext' => Crypto::encrypt($secret)]);
fwrite(STDOUT, "已更新小程序密钥：{$program->program_key}\n");
