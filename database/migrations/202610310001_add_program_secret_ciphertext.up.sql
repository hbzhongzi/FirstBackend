-- 将微信 AppSecret 以 APP_KEY 加密后存入小程序注册表。
ALTER TABLE `{{prefix}}sys_mini_program`
  ADD COLUMN `secret_ciphertext` VARBINARY(1024) NULL COMMENT '微信 AppSecret 加密密文，不保存明文' AFTER `appid`;
