<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Data access for in-app notifications.
 *
 * Notifications are written by services when something happens that another
 * user should know about. They are never authored by a request directly, so the
 * text is composed server-side and does not contain user-supplied markup.
 */
final class NotificationRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Create a notification.
     *
     * A user is never notified about their own action, which is enforced here
     * rather than at each call site so a new caller cannot forget it.
     *
     * @param int $userId Recipient identifier.
     * @param string $type Notification type, one of the schema's ENUM values.
     * @param int|null $actorId User who caused it.
     * @param string $message Human-readable text.
     * @param string $targetType Related entity type.
     * @param int|null $targetId Related entity identifier.
     * @return bool True when a notification was created.
     */
    public function create(
        int $userId,
        string $type,
        ?int $actorId,
        string $message,
        string $targetType = '',
        ?int $targetId = null,
    ): bool {
        if ($actorId !== null && $actorId === $userId) {
            return false;
        }

        $allowed = ['comment', 'like', 'reply', 'system', 'reward'];

        if (!in_array($type, $allowed, true)) {
            return false;
        }

        $this->database->execute(
            'INSERT INTO notifications (user_id, type, actor_id, target_type, target_id, message)
             VALUES (:user_id, :type, :actor_id, :target_type, :target_id, :message)',
            [
                'user_id' => $userId,
                'type' => $type,
                'actor_id' => $actorId,
                'target_type' => mb_substr($targetType, 0, 32),
                'target_id' => $targetId,
                'message' => mb_substr($message, 0, 255),
            ],
        );

        return true;
    }

    /**
     * List a user's notifications, newest first.
     *
     * @param int $userId Recipient identifier.
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Notification rows with actor names.
     */
    public function forUser(int $userId, int $limit = 50): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT n.id, n.type, n.message, n.target_type, n.target_id, n.read_at, n.created_at,
                    pr.display_name AS actor_name
             FROM notifications n
             LEFT JOIN user_profiles pr ON pr.user_id = n.actor_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit',
        );
        $statement->bindValue(':user_id', $userId);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Count a user's unread notifications.
     *
     * @param int $userId Recipient identifier.
     * @return int Number of unread rows.
     */
    public function unreadCount(int $userId): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(*) AS total FROM notifications WHERE user_id = :user_id AND read_at IS NULL',
            ['user_id' => $userId],
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Mark every notification as read.
     *
     * @param int $userId Recipient identifier.
     * @return int Number of rows updated.
     */
    public function markAllRead(int $userId): int
    {
        return $this->database->execute(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND read_at IS NULL',
            ['user_id' => $userId],
        );
    }

    /**
     * Mark one notification as read.
     *
     * The recipient is part of the condition so one user cannot clear another
     * user's notification by guessing its identifier.
     *
     * @param int $notificationId Notification identifier.
     * @param int $userId Recipient identifier.
     * @return bool True when a row was updated.
     */
    public function markRead(int $notificationId, int $userId): bool
    {
        return $this->database->execute(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id AND read_at IS NULL',
            ['id' => $notificationId, 'user_id' => $userId],
        ) > 0;
    }
}
