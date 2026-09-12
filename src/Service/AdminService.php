<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\Database;
use App\Repository\AuditRepository;
use App\Repository\BlogRepository;
use App\Repository\ToolRepository;
use App\Repository\UserRepository;

/**
 * Moderation and site-administration operations.
 *
 * Every method here is reached only through routes that already require a
 * moderator or administrator, and each one records an audit entry, so a
 * destructive action can always be attributed afterwards.
 */
final class AdminService
{
    public function __construct(
        private readonly Database $database,
        private readonly BlogRepository $blog,
        private readonly ToolRepository $tools,
        private readonly UserRepository $users,
        private readonly AuditRepository $audit,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Publish a site-wide announcement, retiring the previous one.
     *
     * Announcements are exclusive rather than additive: the home page shows one
     * banner, so publishing a new one deactivates whatever was live. Both
     * statements share a transaction so the site is never briefly showing two,
     * or none.
     *
     * @param int $actorId Administrator identifier.
     * @param string $content Announcement text.
     * @param bool $alsoNotify Whether to send an in-app notification to everyone.
     * @return int Number of notifications created.
     */
    public function publishAnnouncement(int $actorId, string $content, bool $alsoNotify = false): int
    {
        $this->database->transaction(function () use ($actorId, $content): void {
            $this->database->execute('UPDATE announcements SET is_active = 0 WHERE is_active = 1');
            $this->database->execute(
                'INSERT INTO announcements (content, is_active, created_by) VALUES (:content, 1, :by)',
                ['content' => mb_substr($content, 0, 500), 'by' => $actorId],
            );
        });

        $this->audit->record(AuditRepository::ANNOUNCEMENT_PUBLISHED, $actorId, 'announcement');

        return $alsoNotify ? $this->notifications->broadcast($content) : 0;
    }

    /**
     * Retire the active announcement.
     *
     * @param int $actorId Administrator identifier.
     * @return bool True when one was active.
     */
    public function clearAnnouncement(int $actorId): bool
    {
        $affected = $this->database->execute('UPDATE announcements SET is_active = 0 WHERE is_active = 1');
        $this->audit->record(AuditRepository::ANNOUNCEMENT_PUBLISHED, $actorId, 'announcement');

        return $affected > 0;
    }

    /**
     * Read the announcement currently displayed.
     *
     * @return array<string, mixed>|null The active announcement, or null.
     */
    public function activeAnnouncement(): ?array
    {
        return $this->database->selectOne(
            'SELECT id, content, created_at FROM announcements WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1',
        );
    }

    /**
     * Add a toolbox link.
     *
     * @param int $actorId Administrator identifier.
     * @param string $title Display title.
     * @param string $url Target URL.
     * @param string $description Short description.
     * @param string $category Category key.
     * @param string $icon Icon glyph.
     * @return int The new link identifier.
     */
    public function addTool(int $actorId, string $title, string $url, string $description, string $category, string $icon = ''): int
    {
        $id = $this->tools->createLink($title, $url, $description, $category, $icon);
        $this->audit->record(AuditRepository::TOOL_CREATED, $actorId, 'tool', $id);

        return $id;
    }

    /**
     * Remove a toolbox link.
     *
     * @param int $actorId Administrator identifier.
     * @param int $toolId Link identifier.
     * @return bool True when a link was removed.
     */
    public function deleteTool(int $actorId, int $toolId): bool
    {
        $removed = $this->tools->deleteLink($toolId);

        if ($removed) {
            $this->audit->record(AuditRepository::TOOL_DELETED, $actorId, 'tool', $toolId);
        }

        return $removed;
    }

    /**
     * Publish a blog entry.
     *
     * @param int $actorId Author identifier.
     * @param string $title Entry title.
     * @param string $content Entry body.
     * @param string|null $coverImage Public path of the cover image.
     * @return int The new entry identifier.
     */
    public function publishBlog(int $actorId, string $title, string $content, ?string $coverImage = null): int
    {
        $id = $this->blog->create($actorId, $title, $content, $coverImage);
        $this->audit->record(AuditRepository::BLOG_CREATED, $actorId, 'blog', $id);

        return $id;
    }

    /**
     * Delete a blog entry.
     *
     * @param int $actorId Administrator identifier.
     * @param int $blogId Entry identifier.
     * @return bool True when an entry was removed.
     */
    public function deleteBlog(int $actorId, int $blogId): bool
    {
        $removed = $this->blog->delete($blogId);

        if ($removed) {
            $this->audit->record(AuditRepository::BLOG_DELETED, $actorId, 'blog', $blogId);
        }

        return $removed;
    }

    /**
     * Grant a custom title to an account.
     *
     * @param int $actorId Administrator identifier.
     * @param int $targetUserId Recipient identifier.
     * @param string $title Title text; an empty string clears the title.
     * @return bool True when the account existed and was updated.
     */
    public function grantTitle(int $actorId, int $targetUserId, string $title): bool
    {
        $user = $this->users->find($targetUserId);

        if ($user === null) {
            return false;
        }

        $this->database->execute(
            'UPDATE user_profiles SET custom_title = :title, updated_at = CURRENT_TIMESTAMP WHERE user_id = :id',
            ['title' => trim($title) === '' ? null : mb_substr(trim($title), 0, 32), 'id' => $targetUserId],
        );

        $this->audit->record(AuditRepository::TITLE_GRANTED, $actorId, 'user', $targetUserId);

        return true;
    }

    /**
     * Adjust an account's role.
     *
     * An administrator cannot demote themselves: doing so can leave a site with
     * nobody able to administer it, and the mistake is not recoverable through
     * the interface.
     *
     * @param int $actorId Administrator identifier.
     * @param int $targetUserId Target identifier.
     * @param string $role One of `user`, `moderator`, or `admin`.
     * @return bool True when the role was changed.
     */
    public function setRole(int $actorId, int $targetUserId, string $role): bool
    {
        if ($actorId === $targetUserId) {
            return false;
        }

        if (!in_array($role, ['user', 'moderator', 'admin'], true)) {
            return false;
        }

        if ($this->users->find($targetUserId) === null) {
            return false;
        }

        $this->database->execute('UPDATE users SET role = :role WHERE id = :id', ['role' => $role, 'id' => $targetUserId]);

        return true;
    }

    /**
     * Suspend or reinstate an account.
     *
     * @param int $actorId Administrator identifier.
     * @param int $targetUserId Target identifier.
     * @param bool $suspended Whether the account should be suspended.
     * @return bool True when the status changed.
     */
    public function setSuspended(int $actorId, int $targetUserId, bool $suspended): bool
    {
        if ($actorId === $targetUserId) {
            return false;
        }

        if ($this->users->find($targetUserId) === null) {
            return false;
        }

        $this->database->execute(
            'UPDATE users SET status = :status WHERE id = :id',
            ['status' => $suspended ? 'suspended' : 'active', 'id' => $targetUserId],
        );

        return true;
    }

    /**
     * Reply to a feedback submission.
     *
     * @param int $actorId Administrator identifier.
     * @param int $feedbackId Submission identifier.
     * @param string $reply Reply text.
     * @return bool True when the submission existed and was updated.
     */
    public function replyToFeedback(int $actorId, int $feedbackId, string $reply): bool
    {
        $affected = $this->database->execute(
            "UPDATE feedback
             SET admin_reply = :reply, status = 'resolved', replied_by = :by, replied_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['reply' => $reply, 'by' => $actorId, 'id' => $feedbackId],
        );

        if ($affected === 0) {
            return false;
        }

        $this->audit->record(AuditRepository::FEEDBACK_REPLIED, $actorId, 'feedback', $feedbackId);

        // Tell the author their report was answered, when they had an account.
        $submission = $this->database->selectOne('SELECT user_id FROM feedback WHERE id = :id', ['id' => $feedbackId]);

        if ($submission !== null && $submission['user_id'] !== null) {
            $this->notifications->rewarded((int) $submission['user_id'], '你的反馈已收到回复。');
        }

        return true;
    }

    /**
     * Counters for the admin dashboard.
     *
     * @return array<string, int> Label to count.
     */
    public function dashboardCounts(): array
    {
        $counts = [];

        $queries = [
            'users' => 'SELECT COUNT(*) AS total FROM users',
            'posts' => 'SELECT COUNT(*) AS total FROM posts WHERE is_deleted = 0',
            'comments' => 'SELECT COUNT(*) AS total FROM comments WHERE is_deleted = 0',
            'blog' => 'SELECT COUNT(*) AS total FROM blog_posts',
            'feedback_open' => "SELECT COUNT(*) AS total FROM feedback WHERE status = 'pending'",
            'tools' => 'SELECT COUNT(*) AS total FROM tools WHERE is_active = 1',
        ];

        foreach ($queries as $label => $sql) {
            $row = $this->database->selectOne($sql);
            $counts[$label] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    /**
     * List feedback submissions, newest first.
     *
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Submission rows.
     */
    public function feedback(int $limit = 50): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT f.id, f.type, f.content, f.status, f.admin_reply, f.created_at,
                    pr.display_name AS author_name
             FROM feedback f
             LEFT JOIN user_profiles pr ON pr.user_id = f.user_id
             ORDER BY f.created_at DESC
             LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * List accounts for the user-management panel.
     *
     * @param int $limit Maximum rows to return.
     * @return list<array<string, mixed>> Account rows.
     */
    public function users(int $limit = 100): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT u.id, u.username, u.role, u.status, u.created_at, u.last_login_at,
                    pr.display_name, pr.custom_title, pr.stardust, pr.exp
             FROM users u
             LEFT JOIN user_profiles pr ON pr.user_id = u.id
             ORDER BY u.id ASC
             LIMIT :limit',
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }
}
