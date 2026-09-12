<?php

/**
 * Administration dashboard.
 *
 * @var array<string,int> $counts
 * @var array<string,mixed>|null $announcement
 * @var list<array<string,mixed>> $feedback
 * @var list<array<string,mixed>> $users
 * @var list<array<string,mixed>> $links
 * @var string $csrfField
 * @var string $csrfToken
 * @var array{id:int,name:string,role:string}|null $currentUser
 */

use App\Http\View;

$announcement = $announcement ?? null;
$currentUser = $currentUser ?? null;
$isAdmin = ($currentUser['role'] ?? '') === 'admin';
?>
<section class="feed">
    <header class="feed-header">
        <h1>舰长控制台</h1>
        <a class="btn btn-ghost" href="/admin/audit">查看操作日志</a>
    </header>

    <section class="panel">
        <h2>概览</h2>
        <dl class="stat-grid">
            <div><dt>账号</dt><dd><?= (int) ($counts['users'] ?? 0) ?></dd></div>
            <div><dt>帖子</dt><dd><?= (int) ($counts['posts'] ?? 0) ?></dd></div>
            <div><dt>评论</dt><dd><?= (int) ($counts['comments'] ?? 0) ?></dd></div>
            <div><dt>日志</dt><dd><?= (int) ($counts['blog'] ?? 0) ?></dd></div>
            <div><dt>待处理反馈</dt><dd><?= (int) ($counts['feedback_open'] ?? 0) ?></dd></div>
            <div><dt>导航链接</dt><dd><?= (int) ($counts['tools'] ?? 0) ?></dd></div>
        </dl>
    </section>

    <?php if ($isAdmin): ?>
        <section class="panel">
            <h2>站内广播</h2>

            <?php if ($announcement !== null): ?>
                <div class="alert alert-info">
                    <strong>当前广播：</strong>
                    <?= View::escape($announcement['content']) ?>
                    <span class="muted">（<?= View::escape($announcement['created_at']) ?>）</span>
                </div>
            <?php else: ?>
                <p class="muted">当前没有启用中的广播。</p>
            <?php endif; ?>

            <form method="post" action="/admin/announcement" class="form">
                <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

                <label for="announcement-content">新广播内容</label>
                <input id="announcement-content" name="content" type="text" required minlength="2" maxlength="500">

                <label class="checkbox-row">
                    <input type="checkbox" name="notify" value="1">
                    同时向所有活跃账号发送站内通知
                </label>

                <button type="submit" class="btn btn-primary">发布广播</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>发布日志</h2>

        <form method="post" action="/admin/blog" enctype="multipart/form-data" class="form">
            <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

            <label for="blog-title">标题</label>
            <input id="blog-title" name="title" type="text" required minlength="2" maxlength="160">

            <label for="blog-content">正文（支持 Markdown）</label>
            <textarea id="blog-content" name="content" rows="10" required minlength="10"></textarea>

            <label for="blog-cover">封面图（可选）</label>
            <input id="blog-cover" name="cover" type="file" accept="image/jpeg,image/png,image/gif,image/webp">

            <button type="submit" class="btn btn-primary">发布日志</button>
        </form>
    </section>

    <section class="panel">
        <h2>导航链接（<?= count($links) ?>）</h2>

        <form method="post" action="/admin/tools" class="form form-inline-grid">
            <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">

            <div>
                <label for="tool-title">名称</label>
                <input id="tool-title" name="title" type="text" required maxlength="80">
            </div>
            <div>
                <label for="tool-url">链接</label>
                <input id="tool-url" name="url" type="url" required maxlength="500" placeholder="https://">
            </div>
            <div>
                <label for="tool-category">分类</label>
                <input id="tool-category" name="category" type="text" maxlength="48" value="general">
            </div>
            <div>
                <label for="tool-icon">图标</label>
                <input id="tool-icon" name="icon" type="text" maxlength="16" placeholder="🔧">
            </div>
            <div class="grid-span">
                <label for="tool-description">描述</label>
                <input id="tool-description" name="description" type="text" maxlength="255">
            </div>

            <button type="submit" class="btn btn-primary grid-span">添加链接</button>
        </form>

        <?php if ($links !== []): ?>
            <table class="data-table">
                <thead>
                    <tr><th>名称</th><th>分类</th><th>链接</th><th>操作</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($links as $link): ?>
                        <tr>
                            <td><?= View::escape($link['icon']) ?> <?= View::escape($link['title']) ?></td>
                            <td><?= View::escape($link['category']) ?></td>
                            <td><a href="<?= View::escape($link['url']) ?>" target="_blank" rel="noopener noreferrer">访问</a></td>
                            <td>
                                <form method="post" action="/admin/tools/delete" class="inline-form"
                                      onsubmit="return confirm('确认删除这个链接？');">
                                    <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                                    <input type="hidden" name="tool_id" value="<?= (int) $link['id'] ?>">
                                    <button type="submit" class="link-danger">删除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>账号管理（<?= count($users) ?>）</h2>

        <table class="data-table">
            <thead>
                <tr><th>ID</th><th>代号</th><th>角色</th><th>状态</th><th>星尘</th><th>称号 / 操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><?= View::escape($user['display_name'] ?? $user['username']) ?></td>
                        <td>
                            <?php if ($isAdmin): ?>
                                <form method="post" action="/admin/users/role" class="inline-form">
                                    <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <label class="sr-only" for="role-<?= (int) $user['id'] ?>">角色</label>
                                    <select id="role-<?= (int) $user['id'] ?>" name="role">
                                        <?php foreach (['user', 'moderator', 'admin'] as $role): ?>
                                            <option value="<?= View::escape($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>>
                                                <?= View::escape($role) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="link-action">保存</button>
                                </form>
                            <?php else: ?>
                                <?= View::escape((string) $user['role']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pill <?= $user['status'] === 'active' ? '' : 'pill-danger' ?>">
                                <?= View::escape((string) $user['status']) ?>
                            </span>
                        </td>
                        <td><?= number_format((int) ($user['stardust'] ?? 0)) ?></td>
                        <td>
                            <form method="post" action="/admin/title" class="inline-form">
                                <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <label class="sr-only" for="title-<?= (int) $user['id'] ?>">称号</label>
                                <input id="title-<?= (int) $user['id'] ?>" name="title" type="text" maxlength="32"
                                       value="<?= View::escape($user['custom_title'] ?? '') ?>" placeholder="称号">
                                <button type="submit" class="link-action">授予</button>
                            </form>

                            <form method="post" action="/admin/users/suspend" class="inline-form">
                                <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <input type="hidden" name="action"
                                       value="<?= $user['status'] === 'active' ? 'suspend' : 'reinstate' ?>">
                                <button type="submit" class="link-danger">
                                    <?= $user['status'] === 'active' ? '停用' : '恢复' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>反馈（<?= count($feedback) ?>）</h2>

        <?php if ($feedback === []): ?>
            <p class="muted">还没有收到反馈。</p>
        <?php endif; ?>

        <ul class="feedback-list">
            <?php foreach ($feedback as $item): ?>
                <li class="feedback-item">
                    <div class="feedback-head">
                        <span class="pill"><?= View::escape((string) $item['type']) ?></span>
                        <span><?= View::escape($item['author_name'] ?? '匿名') ?></span>
                        <time datetime="<?= View::escape($item['created_at']) ?>"><?= View::escape($item['created_at']) ?></time>
                        <span class="pill <?= $item['status'] === 'pending' ? 'pill-danger' : '' ?>">
                            <?= View::escape((string) $item['status']) ?>
                        </span>
                    </div>

                    <p class="feedback-content"><?= nl2br(View::escape($item['content'])) ?></p>

                    <?php if (!empty($item['admin_reply'])): ?>
                        <div class="feedback-reply">
                            <strong>已回复：</strong><?= nl2br(View::escape($item['admin_reply'])) ?>
                        </div>
                    <?php else: ?>
                        <form method="post" action="/admin/feedback" class="form">
                            <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                            <input type="hidden" name="feedback_id" value="<?= (int) $item['id'] ?>">

                            <label class="sr-only" for="reply-<?= (int) $item['id'] ?>">回复内容</label>
                            <input id="reply-<?= (int) $item['id'] ?>" name="reply" type="text" required
                                   minlength="1" maxlength="2000" placeholder="回复内容…">

                            <button type="submit" class="btn btn-primary">回复</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</section>
