<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Append-only record of actions worth being able to reconstruct later.
 *
 * The table exists so a moderation decision or a failed sign-in can be
 * answered with evidence rather than guesswork. Nothing deletes from it except
 * the foreign keys when an account is removed, which is deliberate: a log the
 * application can rewrite is not much of a log.
 */
final class AuditRepository
{
    /** Actions this application records. */
    public const LOGIN_FAILED = 'login.failed';
    public const LOGIN_SUCCEEDED = 'login.succeeded';
    public const LOGOUT = 'logout';
    public const POST_CREATED = 'post.created';
    public const POST_DELETED = 'post.deleted';
    public const COMMENT_CREATED = 'comment.created';
    public const BLOG_CREATED = 'blog.created';
    public const BLOG_DELETED = 'blog.deleted';
    public const FEEDBACK_REPLIED = 'feedback.replied';
    public const TOOL_CREATED = 'tool.created';
    public const TOOL_DELETED = 'tool.deleted';
    public const ANNOUNCEMENT_PUBLISHED = 'announcement.published';
    public const TITLE_GRANTED = 'title.granted';

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Record an action.
     *
     * @param string $action Action key from this class's constants.
     * @param int|null $userId Actor, or null when unauthenticated.
     * @param string $targetType Entity type acted on.
     * @param int|null $targetId Entity identifier.
     * @param string $ipAddress Client address.
     * @param string $userAgent Client user agent.
     * @return void
     */
    public function record(
        string $action,
        ?int $userId = null,
        string $targetType = '',
        ?int $targetId = null,
        string $ipAddress = '',
        string $userAgent = '',
    ): void {
        // The timestamp is supplied by PHP rather than by the database's
        // CURRENT_TIMESTAMP, so a row's time and the windows queried below are
        // always measured against the same clock. SQLite reports
        // CURRENT_TIMESTAMP in UTC, which would otherwise put every window query
        // off by the timezone offset.
        $this->database->execute(
            'INSERT INTO audit_logs (user_id, action, target_type, target_id, ip_address, user_agent, created_at)
             VALUES (:user_id, :action, :target_type, :target_id, :ip_address, :user_agent, :created_at)',
            [
                'user_id' => $userId,
                'action' => mb_substr($action, 0, 48),
                'target_type' => mb_substr($targetType, 0, 32),
                'target_id' => $targetId,
                'ip_address' => mb_substr($ipAddress, 0, 45),
                'user_agent' => mb_substr($userAgent, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * List recent entries, newest first.
     *
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Log rows with actor names.
     */
    public function recent(int $limit = 100): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT a.id, a.action, a.target_type, a.target_id, a.ip_address, a.created_at,
                    pr.display_name AS actor_name
             FROM audit_logs a
             LEFT JOIN user_profiles pr ON pr.user_id = a.user_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Count failed sign-in attempts recorded for an address.
     *
     * @param string $ipAddress Client address.
     * @param int $withinMinutes Window to look back over.
     * @return int Number of recorded failures.
     */
    public function recentFailedLogins(string $ipAddress, int $withinMinutes = 60): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(*) AS total FROM audit_logs
             WHERE action = :action AND ip_address = :ip
               AND created_at > :since',
            [
                'action' => self::LOGIN_FAILED,
                'ip' => $ipAddress,
                'since' => date('Y-m-d H:i:s', time() - ($withinMinutes * 60)),
            ],
        );

        return (int) ($row['total'] ?? 0);
    }
}
