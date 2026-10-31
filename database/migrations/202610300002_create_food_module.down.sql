-- 回滚业务表会删除抽取历史、图鉴和反馈，仅允许在已备份的开发/预发环境执行。
DROP TABLE IF EXISTS `{{prefix}}mini_feedback`;
DROP TABLE IF EXISTS `{{prefix}}mini_food_book`;
DROP TABLE IF EXISTS `{{prefix}}mini_food_draw`;
DROP TABLE IF EXISTS `{{prefix}}mini_food_pool`;
DROP TABLE IF EXISTS `{{prefix}}mini_food`;
