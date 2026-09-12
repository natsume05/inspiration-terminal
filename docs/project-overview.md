# 灵感传输终端 · 项目技术说明

> **用途**：面试前通读，用于在脑中重建项目全貌。
> 所有数字来自实际扫描，未经验证的数字一个都没写。
>
> 仓库：`github.com/natsume05/inspiration-terminal` ｜ 远端 HEAD：`9a49b9e`

---

## 一、一句话定位

一个**独立开发、已上线运行**的个人门户网站，把博客、社区与开发者工具箱放进同一个站点，
用原生 PHP + MySQL 手写全部业务逻辑，**无框架、无 Composer 依赖、无前端构建步骤**。

---

## 二、规模（可当场举证的硬数字）

行数按换行符计，改代码就要一起改；`wc -l` 可以在仓库里逐条复核。

```
应用 PHP        63 文件   11,691 行      src/ routes/ templates/ bin/ bootstrap/ config/ public/
测试与工具      16 文件    3,139 行      tests/ tools/
首方前端 JS      8 文件      893 行      public/assets/js/（另含 2 个 vendored 库）
CSS              1 文件    1,678 行      public/assets/css/
────────────────────────────────────────────────────────────
数据表                    23 张
外键                      25 个
唯一键                     7 个
CHECK 约束                 5 个
路由                      45 个
提交                      20 个（原项目 17 + 本次改造 3+）
仓库跟踪文件             100+ 个
```

**测试与验证**
```
单元断言     92 条    3 个套件，跑在 SQLite 内存库，复刻生产 schema
模块校验     83 条    × 2 轮重复执行全通过，也可指向 MySQL 运行
真实引擎校验  6 条    对真实 MariaDB 跑约束探针
静态检查     75 文件   0 违规（含重复具名占位符规则）
文档检查     10 文件   65 条本地链接与锚点
CI                   PHP 8.1 / 8.2 / 8.3 三版本全绿
HTTP 路由    12 条    登录态与未登录态均验证
```

---

## 三、技术栈（精确版）

| 层 | 选型 | 说明 |
|---|---|---|
| 语言 | PHP 8.1+ | `declare(strict_types=1)`，PSR-4 自动加载，**无命名空间外的第三方依赖** |
| 数据库 | MySQL 8 / MariaDB | 手写 SQL + PDO 具名占位符，23 张表 |
| 数据访问 | PDO | 关闭预处理仿真（`ATTR_EMULATE_PREPARES => false`），异常模式，事务封装 |
| 会话 | 原生 session + 自研封装 | 自管存储目录、空闲超时、ID 轮换 |
| 前端 | 原生 HTML / CSS / ES Modules | Flexbox + Grid，**无构建工具、无框架** |
| 图片 | GD | 内容嗅探 → 缩放（保留 alpha）→ 统一转 WebP |
| 外部 API | cURL | GitHub REST（榜单）、CheapShark（Steam 折扣），**服务端代理 + 落库缓存** |
| Markdown | marked + DOMPurify | vendored，`marked` → **净化** → DOM |
| 测试 | 自研轻量框架（~200 行） | 零依赖，`git clone` 后直接可跑 |
| 部署 | Apache / Nginx / Docker | 文档根指向 `public/`，单入口前端控制器 |
| CI | GitHub Actions | 三版本矩阵，语法 + 静态分析 + 测试 |

**为什么不用框架**：项目规模不需要框架的收益，而我把请求生命周期、SQL 边界、
会话安全这些底层链路真正写了一遍。代价我也清楚——框架替我解决的依赖注入、
路由、迁移、模板转义，都要自己写对，所以**测试和文档在这个项目里不是可选项**。

---

## 四、数据库设计（23 张表）

### 4.1 分域

```
用户域     users ──1:1── user_profiles
                      └─ user_profiles 存 exp / stardust / avatar / bio
                         拆开理由：账号字段极少变，经济数值每次操作都变

内容域     posts ──1:N── comments
           posts ──N:M── users  通过 post_likes（复合主键）
           post_categories ──1:N── posts

博客域     blog_posts ──1:N── blog_comments
           blog_posts ──N:M── users  通过 blog_likes
           blog_posts ──N:M── tags   通过 blog_post_tags

经济域     shop_items ──N:M── users  通过 user_items（含 is_equipped）
           与用户域无外键，但通过 users.id 关联

限流域     rate_limits  (user_id, action, window_date) 复合主键
                             通用每日限流，三种玩法共用

运营域     notifications / feedback / announcements / audit_logs / login_attempts

工具箱     tools / github_projects / api_cache
```

### 4.2 三个值得讲的字段级决策

**① `posts.author` 从「用户名」改成 `user_id` 整型外键**

原实现存的是用户名字符串，而个人中心允许改名。改名后
`LEFT JOIN users ON p.author = u.username` 就失配，帖子变成无主：
头像、等级、称号全部丢失。**改名的操作和读帖子的查询在同一个系统里，
却对"作者是谁"有不同理解——这是设计缺陷的典型信号。**

**② 经济数值是 `UNSIGNED` 且带显式 `CHECK (stardust >= 0)`**

两个约束并列，因为 `UNSIGNED` 的行为**依赖数据库的 `sql_mode`**
（见第 14 题：严格模式缺失时会被静默截断）。显式 `CHECK` 不依赖配置。

**③ `rate_limits` 用 `(user_id, action, window_date)` 复合主键**

靠 `INSERT ... ON DUPLICATE KEY UPDATE counter = counter + 1` 做原子自增，
`attempt()` 先占位再判断，超限就把占位还回去。
**决定胜负的是那一条原子语句，而不是应用层的"先读再判断"。**

---

## 五、请求流向

```
HTTP 请求
  ↓
public/index.php                       唯一入口，boot 失败也在这里兜底成 503
  ↓
bootstrap/autoload.php                 自动加载 + 载入 .env + 设定应用时区
  ↓
Kernel::boot()                         配置、错误级别、会话存储、PDO 连接、服务装配
  ↓
Session::start()                       HttpOnly / SameSite / 空闲超时 / ID 轮换
  ↓
Router::dispatch()
  ├─ 非安全方法 → Csrf::verify()        统一强制，handler 不可能漏掉
  └─ 路径匹配（支持 {slug} 参数）
  ↓
控制器闭包（routes/web.php）
  ├─ Validator                         输入校验，失败即拒绝并给出具体错误
  ├─ Service                           业务规则（事务、限流、奖励）
  └─ Repository                        唯一允许出现 SQL 的层
  ↓
View::render()                         模板转义 + 套 layout
  ↓
SecurityHeaders::apply()               CSP / X-Frame-Options / nosniff / HSTS
  ↓
Response::send()
```

**架构约束是被工具强制的**（`tools/lint.php`），不是口头约定：
1. SQL 只允许出现在 `src/Repository/`
2. 业务规则只允许出现在 `src/Service/`
3. 模板输出必须显式转义

---

## 六、四个关键数据流

### 6.1 每日签到（幂等）

```
POST /api/checkin   （需登录 + CSRF token）
  ↓
RateLimitRepository::attempt($userId, 'checkin', $today, cap=1)
  → 一条 upsert 原子地把 counter +1 并读回新值
  → 若 > cap，把占位 -1 还回去，返回 false
  ↓ attempt 成功
数据库事务 {
    EconomyRepository::adjust($userId, +星尘, +经验)
}
  ↓
返回新余额给前端刷新
```

**为什么不是"先查再插"**：两个并发请求都可能读到 0，都认为可以领。
先占位再判断时，**只有一条语句能拿到 1**。

**已知边界（主动说）**：占位是独立语句，如果事务在提交前进程被杀，
占位不会回滚。彻底解决要把占位也放进同一事务。

### 6.2 点赞（唯一键裁决）

```
POST /api/like   {post_id}
  ↓
Validator 校验 post_id 为正整数
  ↓
PostRepository::like()  数据库事务 {
    INSERT IGNORE INTO post_likes (post_id, user_id)      ← 复合主键拒绝重复
    if (影响行数 === 0) return false;                     ← 已存在，不计数
    UPDATE posts SET like_count = like_count + 1
}
  ↓
仅当状态变成 liked 才 rollVoidDrop()（每日一次掉落）
  ↓
返回 state + 服务端权威的 like_count
```

**前端不自己猜计数**，而是用服务端返回的 `like_count`——
它是唯一反映数据库约束结果的值。

### 6.3 购买（余额检查在 WHERE 里）

```
POST /api/shop/purchase   {item_id}
  ↓
findForSale()      商品在售
ownsItem()         未重复购买
  ↓
EconomyRepository::debit()
    UPDATE user_profiles SET stardust = stardust - :amount
    WHERE user_id = :user_id AND stardust >= :amount      ← 检查与扣款同一条语句
    → 影响行数 0 即余额不足
  ↓
数据库事务 { 扣款 + grantItem() }
```

### 6.4 图片上传（分层校验，顺序关键）

```
$_FILES['image']
  1. error === UPLOAD_ERR_OK            传输成功
  2. is_uploaded_file()                 真实 HTTP 上传（挡伪造 tmp_name 指向服务器任意文件）
  3. size <= 上限                       解码之前检查（解码才吃内存）
  4. getimagesize()                     读文件内容判类型（改名 .png 的脚本会被拒）
  5. 像素数 <= 上限                     防"解压炸弹"
  6. GD 重编码 → WebP + 随机文件名      原始文件里的任何东西都过不去
  7. 存到文档根之外                     即使配置错也不可能被当脚本执行
```

**顺序为什么重要**：如果先生成图片再检查大小，那就已经吃过一次内存了。

---

## 七、安全措施与边界

### 已实现

| 威胁 | 措施 |
|---|---|
| SQL 注入 | PDO 具名占位符 + 真实预处理；`LIMIT/OFFSET` 显式绑整型；静态检查拦截插值 |
| XSS | 模板统一 `View::escape()`（有静态检查强制）；JS 用 `textContent` 构造 DOM；CSP `script-src 'self'` |
| CSRF | **路由层统一强制**，非安全方法进入 handler 前校验；token 32 字节随机 + `hash_equals`；登录/登出轮换；`SameSite=Lax` 为第二层 |
| 会话固定 | 登录时轮换 ID；每 15 分钟再轮换；2 小时空闲超时；`use_strict_mode` |
| 密码破解 | `password_hash(PASSWORD_DEFAULT)`；**登录失败 5 次锁定 15 分钟** |
| 用户枚举 | 账号不存在时**照样跑一次密码校验**（dummy hash），使响应时间不泄露账号是否存在 |
| 越权 | 所有用户私有数据的查询**把 `user_id` 写进 SQL 条件**，而非查出来后判断 |
| 上传攻击 | 七层校验（见 6.4） |
| 密钥泄露 | 只从环境变量读，**不保留硬编码回退值** |
| 点击劫持 | `X-Frame-Options: DENY` + CSP `frame-ancestors 'none'` |
| 信息泄露 | `display_errors` 仅调试时开；数据库错误只写日志，不回显驱动信息 |

### 明确的边界（主动承认）

- **登录限流是数据库实现**，多实例部署需换共享存储（Redis）
- **私密笔记密钥在环境变量**，防数据库泄露，不防主机被完全控制；`APP_KEY` 丢失 = 笔记永久不可恢复
- **未做 2FA**，只有密码 + 失败锁定
- **限流表清理未接定时任务**，目前需手动触发 `pruneBefore()`
- **未做真并发集成测试**，并发保证来自数据库约束 + 探针验证，而非多进程测试

---

## 八、本次改造修复的真实缺陷（面试素材）

> 这一节是整份材料里最有价值的部分。**它们不是"我写了什么"，而是"我怎么发现的"。**

| # | 缺陷 | 怎么发现的 | 为什么值得讲 |
|---|---|---|---|
| 1 | **MariaDB 严格模式缺失导致静默数据损坏**：写 `-5` 到 `UNSIGNED` 列实际存 `0`，只报警告；**`CHECK` 约束根本不被求值** | SQLite 上永远不复现，对真实引擎跑约束探针时暴露 | 证明你理解"测试通过 ≠ 正确"，会问"我测的是不是线上同一个引擎" |
| 2 | **时区跨时钟比较**：SQLite 的 `CURRENT_TIMESTAMP` 是 UTC，PHP 用应用时区，差 8 小时；且 CLI 脚本从未设置时区 | 缓存过期与登录失败窗口行为异常 | 只在跨进程、跨引擎时暴露，体现系统级一致性意识 |
| 3 | **静态检查因平台差异从未生效**：Windows 路径分隔符是 `\`，导致模板转义规则的前缀判断永远不匹配；**本地"通过"是因为规则没跑** | CI 在 Linux 上全红 | 极少有候选人能讲"我的门禁工具本身有 bug"。体现对虚假安全感的警惕 |
| 4 | **开发工具留下 WAL 边车文件**：只删 `dev.sqlite`，留下 `-wal`/`-shm`，新进程可能读到"新库 + 旧日志"的混合状态 | 脚本报告灌入成功、另一进程查却是空库，被骗两次 | 诚实承认"被自己的工具骗过"，比声称顺利可信 |
| 5 | **首页从不渲染后台发布的广播**：公告在库里是 active，但首页路由根本没查它——**整个功能没有可见效果** | HTTP 端到端测试 | 端到端测试才能抓到的"功能存在但不可见" |
| 6 | **路由只支持精确匹配**，`{slug}` 动态路由完全无法工作 | 博客详情页直接 404 | — |

### 原实现中被重构掉的缺陷（可对比讲）

| 原缺陷 | 危害 |
|---|---|
| CSRF 只覆盖 2/7 个写入口 | 改密码、点赞、评论、签到、购买、删笔记全部可被跨站伪造 |
| 私密笔记删除用 **GET** | 在论坛贴一张 `<img src="...?del=1">` 即可静默删除他人笔记——**可被实际利用** |
| 权限判定三套并存 | `is_admin()` 判 `id===1`；后台判 `username==='MingMo'`；`role` 字段查了却从不用于鉴权 |
| `posts.author` 存用户名字符串 | 用户改名后历史帖子全部失联 |
| `?v=<?php echo time(); ?>` 做缓存破坏 | 每请求 URL 都不同，**浏览器永不缓存**，自己废掉了配好的长缓存 |
| 评论奖励无限制 | 无限刷虚拟货币，经济系统可被刷崩 |
| 无任何外键 | 删帖子需手动补删点赞，漏写即留孤儿数据 |
| 20 个内联 `<script>` 块 | 逻辑无法复用、无法缓存、无法测试 |

---

## 九、面试时能当场展示的四样东西

**面试前把窗口都开好**：

| 展示 | 打开 | 证明 |
|---|---|---|
| **建表 SQL + ER 关系** | `database/migrations/001_initial_schema.sql` | 23 张表、25 个外键的真实设计 |
| **CI 绿灯** | 仓库 `Actions` 标签页 | 92 条断言在三个 PHP 版本上真实跑过 |
| **分层边界被强制** | `tools/lint.php` | “SQL 只在仓储层”是工具强制的，不是口号 |
| **门禁抓到的真实缺陷** | `php tools/lint.php` 的输出 | 重复具名占位符规则当场抓出 `EconomyRepository::debit()` |
| **一次真实的排查提交** | `git show 66acdee` | 门禁 bug 的完整修复过程与推理 |
| **只在 MySQL 上出现的缺陷** | `git show` 看 `EconomyRepository::debit()` 的改动 | 商店在 SQLite 上全绿、在 MySQL 上整条链路不可用 |

> 最后两条尤其有效。**面试官几乎没见过候选人能展示“我的质量门禁本身有 bug，
> 我是这么发现并修复的”**，或者“我的测试在 SQLite 上全绿，但生产引擎根本跑不通”
> ——这比“我写了 11,000 行代码”更能证明工程成熟度。

---

## 十、快速自测（能答上来就说明准备好了）

1. 23 张表分几个域？`user_profiles` 为什么和 `users` 拆开？
2. 点赞为什么用复合主键而不是加一个 `id`？
3. `attempt()` 为什么要"先占位再判断"？残留窗口在哪？
4. `debit()` 的余额检查为什么写在 `WHERE` 里？
5. 上传校验为什么必须在解码**之前**检查大小？
6. 严格模式缺失会导致什么？为什么 SQLite 测不出来？
7. 时区问题为什么要改成"在 PHP 里算好再绑定"？
8. Markdown 为什么必须过 DOMPurify？不过会怎样？
9. CSRF 现在为什么放在路由层？放在路由层解决了什么问题？
10. `$renderedContent` 为什么不转义？

答不上来的，回去看 `docs/interview-questions.md` 对应题号。
