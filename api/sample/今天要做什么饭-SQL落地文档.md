# “今天要做什么饭”SQL 落地文档

## 1. 目标

本文说明“今天要做什么饭”业务模块的数据库落地方式，配套接口设计见 [今天要做什么饭-后端接口落地说明](./今天要做什么饭-后端接口落地说明.md)。

可执行初始化脚本：

```text
database/food_module.sql
```

业务表已经由 `202610300002_create_food_module.up.sql` / `.down.sql` 纳入版本化迁移，未修改现有公共表迁移，也未把业务表混入 `202610300001_create_public_tables`。`database/food_module.sql` 负责 12 项初始内容 seed；正式生产发布时应先执行 migration，再按目标 `program_key` 导入 seed，并在预发环境完成空库、升级和回滚验证。

## 2. 表清单

| 表名 | 模块 | 作用 |
| --- | --- | --- |
| `mini_food` | 美食内容 | 保存名称、简介、起源、步骤和科普小贴士 |
| `mini_food_pool` | 奖池 | 保存用户在当前小程序启用的美食 |
| `mini_food_draw` | 抽取 | 保存转盘/盲盒每次服务端抽取结果 |
| `mini_food_book` | 图鉴 | 聚合用户已抽到的美食及次数 |
| `mini_feedback` | 反馈 | 保存功能建议、Bug、推荐美食和其他反馈 |

所有业务表都包含 `program_id`。用户相关表还包含 `user_id`，接口查询必须同时限定当前项目和当前用户。

`mini_food` 的 `origin`、`steps`、`tip` 字段已包含在 `202610300002_create_food_module.up.sql` 和 `database/food_module.sql` 中；已有环境只需确认迁移已执行并重新导入或维护内容数据，不要重复新增同名字段。

## 3. 初始化前置条件

1. 已执行公共表初始化，存在 `sys_mini_program`、`sys_user` 等公共表。
2. 目标小程序已经在 `sys_mini_program` 注册并且 `status=1`。
3. 数据库和连接使用 `utf8mb4`，否则 Emoji 字段可能插入失败。
4. 当前脚本默认使用 `program_key='sample'`，导入其他小程序前修改脚本顶部的 `@program_key`。

## 4. 执行方式

Windows PowerShell：

```powershell
mysql -h127.0.0.1 -P3306 -u mytotal_app -p mytotal `
  < database/food_module.sql
```

Linux/macOS：

```bash
mysql -h127.0.0.1 -P3306 -u mytotal_app -p mytotal < database/food_module.sql
```

执行后检查：

```sql
SHOW TABLES LIKE 'mini_food%';
SELECT id, program_id, name, status FROM mini_food ORDER BY sort;
SELECT COUNT(*) FROM mini_food WHERE program_id = <program_id> AND status = 1;
```

脚本使用 `CREATE TABLE IF NOT EXISTS` 和 `INSERT IGNORE`，可重复执行；目标 `program_key` 不存在或已停用时只创建表、不插入初始美食数据。脚本不负责更新已存在记录的内容，也不会删除业务数据。内容修订应使用后台管理操作或新增 migration，不要直接覆盖生产数据。

## 5. 关键字段规则

### `mini_food` 内容详情字段

| 字段 | 类型 | 返回格式 | 用途 |
| --- | --- | --- | --- |
| `origin` | `TEXT NULL` | string 或 `null` | 起源小知识 |
| `steps` | `JSON NOT NULL` | string 数组 | 家常做法步骤，按展示顺序返回 |
| `tip` | `TEXT NULL` | string 或 `null` | 科普小贴士 |

`GET /api/{programKey}/v1/food-book/{food_id}` 和 `GET /api/{programKey}/v1/foods/{food_id}` 共用上述内容字段；图鉴详情另外返回 `draw_count`、`first_drawn_at` 和 `last_drawn_at`。

### `mini_food.steps`

使用 JSON 数组保存步骤，示例：

```json
[
  "备齐食材并完成切配",
  "按耐煮程度依次下锅",
  "调味后装盘"
]
```

后台写入时限制步骤数量、单步长度和总内容长度，禁止把未经处理的 HTML 当作可执行内容返回。

### 幂等键

- `mini_food_draw.idempotency_key` 在 `(program_id, user_id)` 范围内唯一，网络重试时返回原抽取结果。
- `mini_feedback.idempotency_key` 在 `(program_id, user_id)` 范围内唯一；为空时不启用幂等约束。
- 奖池保存采用整体覆盖，不依赖客户端逐条切换请求。

### 图鉴统计

`mini_food_book.draw_count` 只能由抽取 Service 在事务内累加，不能由客户端提交。抽取记录、图鉴聚合和用户统计更新必须保持同一事务边界。

## 6. 默认奖池初始化

脚本只初始化 `mini_food` 内容，不会为不存在的用户预建奖池。新用户首次调用 `GET /food-pool` 或首次进入抽取模块时：

1. 查询当前小程序所有 `status=1` 的美食。
2. 在事务中批量写入 `mini_food_pool`。
3. 再返回启用列表。

如果用户已经保存过奖池，不得因为接口读取而重新全选；平台下架美食只在读取和抽取时排除，不删除用户历史图鉴。

## 7. 回滚与清理

业务表删除会永久丢失抽取历史和反馈，生产环境不建议直接执行。开发环境如需清理，按依赖倒序执行：

```sql
DROP TABLE IF EXISTS `mini_feedback`;
DROP TABLE IF EXISTS `mini_food_book`;
DROP TABLE IF EXISTS `mini_food_draw`;
DROP TABLE IF EXISTS `mini_food_pool`;
DROP TABLE IF EXISTS `mini_food`;
```

正式环境应新增带时间版本的 `up/down` migration，并先完成数据库备份和恢复演练。

## 8. 安全与隔离要求

- 不接受客户端传入的 `program_id`、`user_id` 或抽中 `food_id` 作为可信值。
- `mini_food_pool`、`mini_food_draw`、`mini_food_book`、`mini_feedback` 的所有查询必须带 `program_id`。
- 反馈的 `contact` 只能由有权限的后台人员查看，并在日志、接口错误和运营导出中脱敏。
- 抽取随机结果由服务端生成，转盘动画只展示服务端返回结果。
- SQL 脚本不保存微信 AppSecret、Token、手机号明文或其他密钥。
