<?php

/**
 * Notifications.
 *
 * @var list<array<string,mixed>> $notifications
 * @var string $csrfField
 * @var string $csrfToken
 */

use App\Http\View;

/**
 * Present a notification type as a label.
 *
 * @param string $type Notification type.
 * @return string Human-readable label.
 */
$typeLabel = static function (string $type): string {
    return match ($type) {
        'comment' => '评论',
        'like' => '点赞',
        'reply' => '回复',
        'reward' => '奖励',
        default => '系统',
    };
};
?>
<section class="feed">
    <header class="feed-header">
        <h1>信号记录</h1>

        <?php if ($notifications !== []): ?>
            <form method="post" action="/notifications/read" class="inline-form">
                <input type="hidden" name="<?= View::escape($csrfField) ?>" value="<?= View::escape($csrfToken) ?>">
                <button type="submit" class="btn btn-ghost">全部标为已读</button>
            </form>
        <?php endif; ?>
    </header>

    <?php if ($notifications === []): ?>
        <p class="muted">还没有收到任何信号。</p>
    <?php endif; ?>

    <ul class="notification-list">
        <?php foreach ($notifications as $notification): ?>
            <li class="notification-item <?= $notification['read_at'] === null ? 'is-unread' : '' ?>">
                <span class="notification-type"><?= View::escape($typeLabel((string) $notification['type'])) ?></span>
                <span class="notification-message"><?= View::escape($notification['message']) ?></span>
                <time class="notification-time" datetime="<?= View::escape($notification['created_at']) ?>">
                    <?= View::escape($notification['created_at']) ?>
                </time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
