# 微信小程序后台模板

本项目基于 ThinkPHP 8，定位为服务多个微信小程序的综合后台 API 模板。采用“共享平台能力 + 小程序独立业务域”：登录/基础设施可共享，每个小程序的业务控制器、服务、模型、路由独立维护。当前已落地公共接口、项目上下文、Token 认证和数据库初始化脚本；微信密钥、完整授权策略、迁移流程和各小程序业务仍需按项目配置与开发。

## 环境要求

- PHP 8.0 或更高版本，并启用项目所需扩展（至少 PDO 及对应数据库驱动）
- Composer 2
- MySQL 5.7+ 或兼容数据库
- Redis 3+；项目默认使用 Redis 缓存，PHP 未启用 `ext-redis` 时由 Composer 安装的 Predis 提供客户端

## 本地启动

```bash
composer install
```

当前仓库不提交包含环境值的 `.example.env`。请由部署系统创建 `.env`，按下方综合部署文档填写数据库、Redis 和 `APP_KEY`，再将 Web 服务器站点根目录指向项目的 `public/`。Apache 需要启用 `mod_rewrite` 并允许站点目录使用 `.htaccess`；本地也可用 ThinkPHP 内置服务器：

```bash
php think run
```

初始化或升级数据库：

```bash
php database/migrate.php status
php database/migrate.php up
```

首次部署、环境变量、数据库迁移和 Nginx 配置见 [docs/项目初始化部署文档.md](docs/项目初始化部署文档.md)。

“今天吃什么”项目从数据库注册、微信密钥、业务 seed 到线上验收的完整操作见 [docs/今天吃什么项目落地与线上部署操作说明.md](docs/今天吃什么项目落地与线上部署操作说明.md)。

示例租户健康检查：`GET /api/sample/v1/health`。预期返回统一 JSON 格式。生产环境请关闭调试、禁止 Web 访问项目根目录，并通过 HTTPS 提供服务。

## API 约定

- 路径按 `/api/{programKey}/v1/...` 版本化；项目在 `config/mini_programs.php` 注册，业务路由和代码位于对应独立目录。
- 控制器只处理 HTTP 输入输出；项目业务流程放到 `app/mini/{programKey}/service/`，稳定共享能力放 `app/common/`。
- API 响应使用 `code`、`message`、`data`、`timestamp` 四个字段。`code=0` 表示业务成功；HTTP 状态码仍用于表达协议层状态。
- 所有客户端输入都必须验证；不要信任小程序传来的用户 ID、角色或价格。
- 不要把微信 `AppSecret`、数据库密码、签名密钥提交到版本库。微信登录应由服务端调用 `jscode2session`，并实现安全的会话/令牌签发、过期、撤销和权限校验。

## 目录结构

目录说明见 [doce/目录结构说明.md](doce/目录结构说明.md)，项目操作与部署见 [docs/项目初始化部署文档.md](docs/项目初始化部署文档.md)，接口、隔离、认证、测试和发布要求见 [docs/项目开发规范.md](docs/项目开发规范.md)。完整文档入口见 [docs/项目文档索引.md](docs/项目文档索引.md)。

公共接口、数据库设计、兼容迁移和联调验收见 [api/common.md](api/common.md)；接口文档入口见 [api/README.md](api/README.md)。各小程序业务接口按 `api/{programKey}/` 分目录维护；`api/upload.md` 和 `api/user.md` 保留公共兼容说明。“今天要做什么饭”业务的 SQL 与开发状态见 [小程序更新文档](doce/今天要做什么饭开发更新说明.md)。

## 当前边界

模板包含项目注册、路由隔离、Token 认证、基于 EasyWeChat 的微信登录适配、图片固定目录存储和按项目分区日志，但这不等于完整的业务授权系统。本模板不是完整的微信开放平台 SDK，也没有预置 RBAC、支付或内容安全审核服务。接入真实项目时，必须按开发规范补充认证授权、审核策略、日志保留和数据隔离，不能只检查是否携带任意 Token。

