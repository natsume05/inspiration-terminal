# 灵感传输终端 · Inspiration Terminal

> 一个用原生 PHP 手写的个人门户与社区网站：博客、匿名社区、游戏化经济系统与开发者工具箱。
> **无框架、无 Composer 依赖、无前端构建步骤** —— `git clone` 之后就能跑。

[![Tests](https://github.com/natsume05/inspiration-terminal/actions/workflows/ci.yml/badge.svg)](https://github.com/natsume05/inspiration-terminal/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1)
![License](https://img.shields.io/badge/License-MIT-green)

---

## 这是什么

一个单人在业余时间做完并上线的全栈项目。它把三类东西放进同一个站点：

| 板块 | 内容 |
|---|---|
| **深空日志** | 博客，支持封面图与 Markdown |
| **虚空枢纽** | 匿名社区：发帖、评论、点赞、频道筛选、图片上传 |
| **虚空经济** | 星尘虚拟货币：每日签到、概率掉落、分级抽奖、装备穿戴 |
| **提瓦特百宝箱** | 工具箱：GitHub 开源榜单、Steam 折扣监控、链接导航 |

---

## 技术栈

| 层 | 选型 | 说明 |
|---|---|---|
| 语言 | PHP 8.1+ | 严格类型（`declare(strict_types=1)`），PSR-4 自动加载 |
| 数据库 | MySQL 8 / MariaDB | 手写 SQL + 预处理语句，23 张表 |
| 数据访问 | PDO | 具名占位符，关闭预处理仿真，事务封装 |
| 前端 | 原生 HTML / CSS / ES Modules | Flexbox + Grid，无构建工具、无框架 |
| 测试 | 自研轻量测试框架 | 78 条断言，跑在 SQLite 上复刻生产 schema |
| 部署 | Apache / Nginx | 文档根指向 `public/`，单入口前端控制器 |

**为什么不用框架？** 这个项目的规模不需要框架的收益，而我更想把请求生命周期、SQL 边界、
会话与安全这些底层链路真正写一遍。代价我也清楚：依赖注入、路由、迁移、模板转义这些
框架替我解决的问题，都需要我自己写对，因此测试和文档在本项目里不是可选项。

---

## 快速开始

### 方式一：Docker（不需要装任何东西）

```bash
git clone https://github.com/natsume05/inspiration-terminal.git
cd inspiration-terminal
cp .env.example .env
# 生成一个应用密钥填进 APP_KEY
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
docker compose up -d
```

打开 <http://localhost:8080>，用 `demo@example.com` / `demo-password` 登录。

### 方式二：本地 XAMPP / 已有 PHP 环境

```bash
git clone https://github.com/natsume05/inspiration-terminal.git
cd inspiration-terminal
cp .env.example .env
```

编辑 `.env`，至少填好 `APP_KEY` 与 `DB_*`：

```bash
php bin/migrate.php     # 建表
php bin/seed.php        # 写入示例数据（可选）
php -S 127.0.0.1:8080 -t public
```

> **文档根必须指向 `public/`**，而不是项目根目录。`public/index.php` 是唯一的入口文件，
> 配置、迁移脚本、测试都在它之外，因此从 HTTP 层面无法访问。

### 只想看看界面、不想装数据库

```bash
php bin/dev-sqlite.php
DB_DRIVER=sqlite DB_SQLITE_PATH=storage/dev.sqlite \
APP_KEY=0123456789abcdef0123456789abcdef \
php -S 127.0.0.1:8080 -t public
```

---

## 架构

```
inspiration-terminal/
├── public/                 # 唯一的 Web 根
│   ├── index.php           # 前端控制器
│   └── assets/             # CSS 与 ES Module
├── src/
│   ├── Database/           # 连接、迁移器、SQLite 方言适配
│   ├── Http/               # 请求、响应、路由、内核、视图
│   ├── Repository/         # 数据访问，SQL 只出现在这一层
│   ├── Security/           # 会话、CSRF、校验、加密、上传、安全头
│   ├── Service/            # 业务规则
│   └── Support/            # 配置与环境变量
├── routes/web.php          # 路由表
├── templates/              # 视图
├── database/migrations/    # 迁移，按序号执行
├── tests/                  # 测试套件
├── bin/                    # 命令行入口
└── docs/                   # 架构、数据库、安全模型、部署
```

**请求流向**：`public/index.php` → `Kernel`（装配依赖）→ `Router`（CSRF 校验）→
控制器闭包 → `Repository`（SQL）→ `View`（转义渲染）→ `Response`（安全头）。

关键设计约束：**SQL 只允许出现在 `src/Repository/`**，业务规则只允许出现在 `src/Service/`，
模板只负责渲染。`tools/lint.php` 会检查这些边界。

---

## 测试

```bash
php tests/run.php            # 全部套件
php tests/run.php --verbose   # 显示每条断言
php tools/lint.php            # 静态检查
```

测试跑在 SQLite 上，但用的是**同一份生产 schema**：迁移文件是 MySQL DDL，
`SqliteDialect` 负责翻译，因此外键、唯一约束、CHECK 约束在测试中同样生效。

覆盖的关键行为：

- 签到当天只能领取一次；重复请求不会污染每日计数器
- 评论奖励每天封顶，无法刷取星尘
- 购买失败不会扣款；重复购买不会重复扣款；余额不会变负
- 点赞依赖唯一键去重，双击不会刷高赞数
- 作者改名后，历史帖子依然能正确关联到同一账号
- 上传：伪装成图片的脚本、超大文件、非 HTTP 上传来源全部被拒绝
- 私密笔记：AES-GCM 加解密往返、篡改密文会被拒绝、换密钥无法解密

---

## 安全

完整模型见 [`docs/security.md`](docs/security.md)。要点：

| 威胁 | 措施 |
|---|---|
| SQL 注入 | 全部使用 PDO 具名占位符，关闭预处理仿真 |
| XSS | 模板统一走 `View::escape()`；前端用 `textContent` 构造 DOM |
| CSRF | 路由层统一强制校验，写接口无法遗漏 |
| 密码破解 | `password_hash(PASSWORD_DEFAULT)` + 登录失败锁定 |
| 会话固定 | 登录时轮换 Session ID；Cookie 为 `HttpOnly` + `SameSite` |
| 越权 | 所有查询把 `user_id` 作为 SQL 条件，而非查出来后判断 |
| 上传攻击 | 内容嗅探校验类型、限制体积与像素、重新编码为 WebP |
| 密钥泄露 | 只从环境变量读取；`.env` 已被 `.gitignore` 排除 |
| 点击劫持 | `X-Frame-Options: DENY` + CSP `frame-ancestors 'none'` |
| 静态文件被当脚本执行 | 上传目录在文档根之外，由前端控制器转发 |

---

## 文档

| 文档 | 内容 |
|---|---|
| [`docs/security.md`](docs/security.md) | 威胁模型、已实现措施、已知边界，以及 strict mode 问题的完整说明 |
| [`docs/deployment.md`](docs/deployment.md) | 从 XAMPP 到生产服务器的完整步骤 |
| [`public/assets/js/vendor/README.md`](public/assets/js/vendor/README.md) | 前端依赖库的版本、许可与为何随仓库分发 |

---

## 功能模块

| 路由 | 说明 | 权限 |
|---|---|---|
| `/` | 首页，展示管理员发布的站内广播与频道列表 | 公开 |
| `/blog`、`/blog/{slug}` | 日志列表与详情，Markdown 渲染，支持访客评论与登录用户点赞 | 公开 |
| `/community` | 社区动态：发帖、评论、点赞、频道筛选、图片上传 | 需登录 |
| `/notes` | 思维殿堂：私密笔记，AES-256-GCM 加密存储 | 需登录 |
| `/notifications` | 信号记录：评论、点赞、奖励与系统通知 | 需登录 |
| `/feedback` | 信号塔：提交缺陷与建议，收到回复时通知 | 需登录 |
| `/tools` | 百宝箱：导航聚合、GitHub 榜单检索、Steam 折扣监控 | 公开 |
| `/admin` | 舰长控制台：广播、日志发布、导航管理、账号与反馈处理 | 版主／管理员 |
| `/admin/audit` | 操作日志，记录登录与增删改的关键动作 | 版主／管理员 |

---

## 已知边界

这个项目是诚实交付的，下面这些限制是明确的，不是疏忽：

- **没有使用框架**，因此路由与模板能力是刻意做小的；复杂项目应当选框架。
- **没有队列**，图片处理与外部 API 调用都在请求内同步完成。
- **没有多语言**，界面文案只有中文。
- **限流是数据库实现**，多实例部署需要换成 Redis 之类的共享存储。
- **`bin/migrate-legacy.php` 只支持 MySQL**，它按定义就是从旧的 MySQL 安装读取数据，
  因此不像仓储层那样需要兼容 SQLite，也不在自动化测试覆盖范围内。
- **日志 Markdown 在浏览器渲染**，因此关闭 JavaScript 时显示为纯文本（`<noscript>` 回退）。
  渲染路径固定为 `marked` → `DOMPurify` → DOM，绝不直接插入 `marked` 的输出。

---

## 许可

MIT，见 [LICENSE](LICENSE)。前端依赖库的许可见其 vendored 说明。
