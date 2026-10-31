-- 今天要做什么饭：业务模块表结构与初始美食数据
-- MySQL 5.7+/8.0，数据库字符集必须为 utf8mb4。
-- 使用前确认 sys_mini_program 已存在，并按目标小程序修改 @program_key。
-- 本脚本是业务 SQL 参考/初始化脚本，不会自动写入 database/migrate.php 的迁移记录。

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @program_key := 'sample';
SET @program_id := (
  SELECT `id`
  FROM `sys_mini_program`
  WHERE `program_key` = @program_key
    AND `status` = 1
  LIMIT 1
);

-- 美食内容表：每个小程序可维护自己的美食内容和奖池。
CREATE TABLE IF NOT EXISTS `mini_food` (
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
  `sort` INT NOT NULL DEFAULT 0 COMMENT '展示排序，数值越小越靠前',
  `created_at` DATETIME NOT NULL COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_program_food_name` (`program_id`, `name`),
  KEY `idx_program_status_sort` (`program_id`, `status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='今天要做什么饭美食内容';

-- 用户奖池表：存在即表示当前用户已启用该美食。
CREATE TABLE IF NOT EXISTS `mini_food_pool` (
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

-- 抽取记录表：转盘和盲盒共用，结果必须由服务端生成。
CREATE TABLE IF NOT EXISTS `mini_food_draw` (
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

-- 图鉴聚合表：由抽取记录事务内累加，客户端不能直接写次数。
CREATE TABLE IF NOT EXISTS `mini_food_book` (
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

-- 建议反馈表：支持功能建议、Bug、推荐美食和其他反馈。
CREATE TABLE IF NOT EXISTS `mini_feedback` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '反馈主键',
  `program_id` BIGINT UNSIGNED NOT NULL COMMENT '所属小程序 ID',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '提交用户 ID',
  `type` VARCHAR(32) NOT NULL COMMENT '类型：feature、bug、food_recommendation、other',
  `content` TEXT NOT NULL COMMENT '反馈内容',
  `food_name` VARCHAR(64) NULL COMMENT '推荐美食名称',
  `contact` VARCHAR(128) NULL COMMENT '可选联系方式，需脱敏展示',
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

-- 初始美食数据。重复执行不会重复插入相同小程序下的名称。
-- 若 @program_key 不存在或已停用，@program_id 为空，以下插入会跳过；请先注册并启用小程序。
INSERT IGNORE INTO `mini_food`
  (`program_id`, `name`, `emoji`, `description`, `origin`, `steps`, `tip`, `status`, `sort`, `created_at`, `updated_at`)
SELECT `program_id`, `name`, `emoji`, `description`, `origin`, `steps`, `tip`, `status`, `sort`, `created_at`, `updated_at`
FROM (
  SELECT @program_id AS `program_id`, '火锅' AS `name`, '🍲' AS `emoji`, '约上三五好友，热气腾腾最热闹。' AS `description`, '源于长江沿岸的船工饮食，冬日围炉煮食御寒，后发展为南北方各具特色的火锅文化。' AS `origin`, JSON_ARRAY('熬制锅底：清汤用骨汤，麻辣用豆瓣、花椒、牛油炒香', '备齐食材：肉片、丸滑、蔬菜、豆制品切配装盘', '汤沸下料：先肉后菜，涮至变色即食', '蘸料随心：麻酱、蒜泥、油碟按口味调配') AS `steps`, '高温短时涮煮可杀菌，但久煮的汤底嘌呤升高，痛风人群及高尿酸者宜少喝汤、多吃菜。' AS `tip`, 1 AS `status`, 10 AS `sort`, NOW() AS `created_at`, NOW() AS `updated_at`
  UNION ALL SELECT @program_id, '烧烤', '🍢', '夜宵之王，烤什么都香。', '可追溯至游牧民族与原始烤肉，现代烧烤在炭火与调料加持下成为全民夜宵。', JSON_ARRAY('食材腌制：肉串用酱油、料酒、孜然腌入味', '生火预热：炭火烧至无明火、通红为佳', '上架翻烤：不断翻面，避免单面焦糊', '刷酱撒料：中途刷油酱，出炉撒孜然辣椒面'), '明火直烤易产生苯并芘等致癌物，建议搭配大量蔬菜、避免烤焦，电烤或锡纸包裹更健康。', 1, 20, NOW(), NOW()
  UNION ALL SELECT @program_id, '麻辣烫', '🍜', '一个人也能拥有的小幸福。', '川渝地区的市井小吃，由火锅衍生出一人一锅的烫煮形式。', JSON_ARRAY('调底料：麻辣牛油底或骨汤加辣', '自选食材：叶菜、菌菇、丸滑、豆制品', '下锅烫熟：按耐煮程度先后下料', '出锅调味：加麻酱、蒜泥、葱花拌匀'), '自由度高的搭配易做到荤素均衡；注意汤底偏油偏咸，可要求少油少盐、多放蔬菜。', 1, 30, NOW(), NOW()
  UNION ALL SELECT @program_id, '寿司', '🍣', '清爽不油腻，轻食首选。', '源自东南亚保存鱼肉的发酵法，传入日本后演变为醋饭握寿司。', JSON_ARRAY('煮醋饭：米饭拌入寿司醋放凉', '处理鱼生：极新鲜鱼贝类去皮去刺切薄片', '握制：手蘸水取醋饭，轻压铺鱼生', '搭配：蘸少量酱油、佐山葵'), '生食对新鲜度要求极高，孕妇、儿童、免疫力弱者建议选择熟寿司。', 1, 40, NOW(), NOW()
  UNION ALL SELECT @program_id, '披萨', '🍕', '拉丝芝士，快乐加倍。', '意大利那不勒斯的传统 Flatbread，19 世纪加入番茄芝士成为经典。', JSON_ARRAY('揉面发酵：高筋粉加酵母揉匀发酵', '擀饼抹酱：擀圆饼底，抹番茄酱', '铺料：撒马苏里拉芝士与配料', '高温烘烤：220℃以上烤至边缘焦脆'), '高温让芝士发生美拉德反应而香浓，但整体热量偏高，可选薄底、多蔬菜、少培根的版本。', 1, 50, NOW(), NOW()
  UNION ALL SELECT @program_id, '汉堡', '🍔', '一口下去，烦恼全无。', '19 世纪由德国汉堡港传入美国，演变为夹肉饼的圆面包快餐。', JSON_ARRAY('调肉饼：牛肉末加盐胡椒捏成饼', '煎烤肉饼：中大火煎至两面焦香', '切配料：生菜、番茄、洋葱洗净切片', '组装：面包底、酱、菜、肉饼、面包顶'), '自制可换成全麦面包、鸡胸肉、多放蔬菜，比市售快餐更健康均衡。', 1, 60, NOW(), NOW()
  UNION ALL SELECT @program_id, '沙拉', '🥗', '今日份自律，从一碗菜开始。', '源于地中海饮食，以生食蔬菜为基础的健康餐形式。', JSON_ARRAY('选蔬菜：生菜、苦苣、芝麻菜等洗净', '搭配：加番茄、黄瓜、坚果、鸡胸或虾仁', '切配装碗：控干水分避免稀释酱汁', '调酱：油醋汁拌匀'), '生食保留更多维生素，但务必洗净；酱料常是热量陷阱，油醋汁优于奶油酱。', 1, 70, NOW(), NOW()
  UNION ALL SELECT @program_id, '牛肉面', '🍜', '汤浓面劲，元气满满。', '兰州清汤牛肉面与台湾红烧牛肉面各具代表，是国民级面食。', JSON_ARRAY('熬汤：牛骨与香料小火慢炖数小时', '烧肉：牛腩加酱油、豆瓣红烧入味', '煮面：面条下锅煮至筋道', '组合：捞面浇汤，铺肉撒葱辣'), '长时间熬煮释出胶原与风味，选全麦或杂粮面、多加青菜可提升膳食纤维。', 1, 80, NOW(), NOW()
  UNION ALL SELECT @program_id, '饺子', '🥟', '热气腾腾，家的味道。', '中国北方传统面食，春节更岁交子的象征，寓意团圆。', JSON_ARRAY('和面醒面：面粉加水揉成光滑面团醒发', '调馅：肉菜剁碎加调料顺向搅上劲', '擀皮包馅：皮中放馅捏紧花边', '煮、蒸或煎：水开下饺，浮起即熟'), '荤素搭配的馅料营养较均衡；全麦皮、多菜少肉可减少热量，煮食比煎炸更清爽。', 1, 90, NOW(), NOW()
  UNION ALL SELECT @program_id, '盖浇饭', '🍚', '一锅出，最快解决一餐。', '中式快餐经典，米饭浇上现炒菜肴，便捷管饱。', JSON_ARRAY('煮饭：米饭蒸熟备用', '炒菜或卤：选宫保鸡丁、番茄蛋或红烧类', '勾芡收汁：让汤汁裹住食材', '浇饭：热菜连汁铺在米饭上'), '一餐同时含主食与菜，注意蔬菜比例偏低，建议搭配一份清炒时蔬更均衡。', 1, 100, NOW(), NOW()
  UNION ALL SELECT @program_id, '炒饭', '🍳', '剩饭变宝藏，简单又满足。', '家常智慧，用隔夜饭快速做出的一道家常主食。', JSON_ARRAY('备料：隔夜饭打散，蛋、葱、配菜切丁', '炒蛋：先滑散鸡蛋盛起', '炒饭：下饭翻炒至粒粒分开', '调味：加酱油、盐，回锅蛋与配料炒匀'), '隔夜饭水分少、粒粒干爽不黏；少油快炒、多配蔬菜鸡蛋，是很好的清冰箱健康餐。', 1, 110, NOW(), NOW()
  UNION ALL SELECT @program_id, '螺蛳粉', '🍜', '闻着臭吃着香，真香警告！', '广西柳州特色米粉，以酸笋汤底闻名，近年成为网红美食。', JSON_ARRAY('泡粉：干米粉提前泡软煮熟', '熬汤：螺蛳与酸笋、香料熬制汤底', '备料：炸腐竹、花生、酸豆角、木耳', '组合：粉入碗，浇汤放料，淋辣油'), '独特气味来自酸笋发酵产生的硫化物，本身无害；汤底偏油咸，适量享用即可。', 1, 120, NOW(), NOW()
) AS `seed`
WHERE `seed`.`program_id` IS NOT NULL;

-- 说明：新用户首次读取奖池时，应由业务 Service 将当前项目 status=1 的 mini_food 全量写入 mini_food_pool。
