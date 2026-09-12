<?php

/**
 * Audit log.
 *
 * @var list<array<string,mixed>> $entries
 */

use App\Http\View;
?>
<section class="feed">
    <header class="feed-header">
        <h1>操作日志</h1>
        <a class="btn btn-ghost" href="/admin">返回控制台</a>
    </header>

    <p class="muted">
        记录登录、发布与删改等关键动作，用于事后追溯。条目只增不改。
    </p>

    <?php if ($entries === []): ?>
        <p class="muted">还没有记录。</p>
    <?php endif; ?>

    <?php if ($entries !== []): ?>
        <table class="data-table">
            <thead>
                <tr><th>时间</th><th>操作</th><th>操作者</th><th>对象</th><th>来源 IP</th></tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= View::escape($entry['created_at']) ?></td>
                        <td><code><?= View::escape($entry['action']) ?></code></td>
                        <td><?= View::escape($entry['actor_name'] ?? '（未登录）') ?></td>
                        <td>
                            <?php if (!empty($entry['target_type'])): ?>
                                <?= View::escape($entry['target_type']) ?>
                                <?= $entry['target_id'] !== null ? '#' . (int) $entry['target_id'] : '' ?>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= View::escape($entry['ip_address']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
