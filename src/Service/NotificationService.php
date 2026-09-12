<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\NotificationRepository;
use App\Repository\UserRepository;

/**
 * Composes notifications from domain events.
 *
 * The text is built here rather than at each call site, so every notification
 * reads consistently and no template has to assemble a sentence from fragments.
 */
final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * Tell an author that somebody commented on their work.
     *
     * @param int $authorId Recipient identifier.
     * @param int $actorId Commenter identifier.
     * @param string $targetType Either `post` or `blog`.
     * @param int $targetId Entity that was commented on.
     * @return bool True when a notification was created.
     */
    public function commented(int $authorId, int $actorId, string $targetType, int $targetId): bool
    {
        return $this->notifications->create(
            $authorId,
            'comment',
            $actorId,
            sprintf('%s 评论了你的%s。', $this->actorName($actorId), $targetType === 'blog' ? '日志' : '帖子'),
            $targetType,
            $targetId,
        );
    }

    /**
     * Tell an author that somebody liked their work.
     *
     * @param int $authorId Recipient identifier.
     * @param int $actorId Liker identifier.
     * @param string $targetType Either `post` or `blog`.
     * @param int $targetId Entity that was liked.
     * @return bool True when a notification was created.
     */
    public function liked(int $authorId, int $actorId, string $targetType, int $targetId): bool
    {
        return $this->notifications->create(
            $authorId,
            'like',
            $actorId,
            sprintf('%s 赞了你的%s。', $this->actorName($actorId), $targetType === 'blog' ? '日志' : '帖子'),
            $targetType,
            $targetId,
        );
    }

    /**
     * Announce something to every active account.
     *
     * @param string $message Announcement text.
     * @return int Number of notifications created.
     */
    public function broadcast(string $message): int
    {
        $created = 0;

        foreach ($this->users->activeUserIds() as $userId) {
            if ($this->notifications->create($userId, 'system', null, $message, 'announcement')) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Tell a user about a reward they received.
     *
     * @param int $userId Recipient identifier.
     * @param string $message Reward description.
     * @return bool True when a notification was created.
     */
    public function rewarded(int $userId, string $message): bool
    {
        return $this->notifications->create($userId, 'reward', null, $message, 'economy');
    }

    /**
     * @param int $userId Recipient identifier.
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Notification rows.
     */
    public function forUser(int $userId, int $limit = 50): array
    {
        return $this->notifications->forUser($userId, $limit);
    }

    /**
     * @param int $userId Recipient identifier.
     * @return int Number of unread notifications.
     */
    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }

    /**
     * @param int $userId Recipient identifier.
     * @return int Number of rows marked read.
     */
    public function markAllRead(int $userId): int
    {
        return $this->notifications->markAllRead($userId);
    }

    /**
     * @param int $notificationId Notification identifier.
     * @param int $userId Recipient identifier.
     * @return bool True when a row was marked read.
     */
    public function markRead(int $notificationId, int $userId): bool
    {
        return $this->notifications->markRead($notificationId, $userId);
    }

    /**
     * Resolve an actor's display name, falling back to a neutral label.
     *
     * @param int $userId Actor identifier.
     * @return string Name to place in the message.
     */
    private function actorName(int $userId): string
    {
        $user = $this->users->find($userId);

        if ($user === null) {
            return '有人';
        }

        $name = trim((string) ($user['display_name'] ?? ''));

        return $name !== '' ? $name : (string) $user['username'];
    }
}
