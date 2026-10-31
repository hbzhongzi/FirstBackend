-- “今天要做什么饭”业务表：美食、奖池、抽取、图鉴和反馈。
CREATE TABLE IF NOT EXISTS `{{prefix}}mini_food` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '美食主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `name` VARCHAR(64) NOT NULL COMMENT '美食名称',
  `emoji` VARCHAR(16) NULL COMMENT '展示 Emoji',
  `image_url` VARCHAR(512) NULL COMMENT '美食图片地址',
  `description` VARCHAR(255) NOT NULL COMMENT '美食简介',
  `origin` TEXT NULL COMMENT '起源小知识',
  `steps` JSON NOT NULL COMMENT '家常做法步骤 JSON 数组',
  `tip` TEXT NULL COMMENT '科普小贴士',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1 启用，0 停用',
  `sort` INT NOT NULL DEFAULT 0 COMMENT '展示排序',
  `created_at` DATETIME NOT NULL COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_food_name` (`program_id`, `name`),
  KEY `idx_program_status_sort` (`program_id`, `status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='今天要做什么饭美食内容';

CREATE TABLE IF NOT EXISTS `{{prefix}}mini_food_pool` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '奖池记录主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户 ID',
  `food_id` BIGINT UNSIGNED NOT NULL COMMENT '美食 ID',
  `created_at` DATETIME NOT NULL COMMENT '加入时间',
  `updated_at` DATETIME NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_user_food` (`program_id`, `user_id`, `food_id`),
  KEY `idx_program_user` (`program_id`, `user_id`),
  KEY `idx_program_food` (`program_id`, `food_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='今天要做什么饭用户奖池';

CREATE TABLE IF NOT EXISTS `{{prefix}}mini_food_draw` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '抽取记录主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户 ID',
  `food_id` BIGINT UNSIGNED NOT NULL COMMENT '抽中的美食 ID',
  `mode` VARCHAR(16) NOT NULL COMMENT '抽取方式：wheel 或 box',
  `idempotency_key` VARCHAR(128) NOT NULL COMMENT '用户请求幂等键',
  `request_id` VARCHAR(128) NULL COMMENT 'API 请求 ID',
  `created_at` DATETIME NOT NULL COMMENT '抽取时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_user_idempotency` (`program_id`, `user_id`, `idempotency_key`),
  KEY `idx_program_user_time` (`program_id`, `user_id`, `created_at`),
  KEY `idx_program_food_time` (`program_id`, `food_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='今天要做什么饭抽取记录';

CREATE TABLE IF NOT EXISTS `{{prefix}}mini_food_book` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '图鉴记录主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户 ID',
  `food_id` BIGINT UNSIGNED NOT NULL COMMENT '美食 ID',
  `draw_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '抽到次数',
  `first_drawn_at` DATETIME NOT NULL COMMENT '首次抽到时间',
  `last_drawn_at` DATETIME NOT NULL COMMENT '最近抽到时间',
  `created_at` DATETIME NOT NULL COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_user_book_food` (`program_id`, `user_id`, `food_id`),
  KEY `idx_program_user_last_drawn` (`program_id`, `user_id`, `last_drawn_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='今天要做什么饭用户图鉴';

CREATE TABLE IF NOT EXISTS `{{prefix}}mini_feedback` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '反馈主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '提交用户 ID',
  `type` VARCHAR(32) NOT NULL COMMENT '类型：feature、bug、food_recommendation、other',
  `content` TEXT NOT NULL COMMENT '反馈内容',
  `food_name` VARCHAR(64) NULL COMMENT '推荐美食名称',
  `contact` VARCHAR(128) NULL COMMENT '可选联系方式',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT '状态：pending、processing、resolved、rejected',
  `idempotency_key` VARCHAR(128) NULL COMMENT '提交幂等键',
  `handled_by` BIGINT UNSIGNED NULL COMMENT '后台处理人 ID',
  `handled_at` DATETIME NULL COMMENT '处理时间',
  `created_at` DATETIME NOT NULL COMMENT '提交时间',
  `updated_at` DATETIME NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_user_feedback_key` (`program_id`, `user_id`, `idempotency_key`),
  KEY `idx_program_user_time` (`program_id`, `user_id`, `created_at`),
  KEY `idx_program_status_time` (`program_id`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='今天要做什么饭用户反馈';
