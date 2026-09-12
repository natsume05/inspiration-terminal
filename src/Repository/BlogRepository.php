<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use App\Support\Excerpt;

/**
 * Data access for blog posts and their comments.
 *
 * Separate from the community feed on purpose: blog entries are written by the
 * site owner, are addressed by slug for stable links, and accept comments from
 * signed-out readers, while community posts belong to members and are addressed
 * by numeric id.
 */
final class BlogRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * List published entries, newest first.
     *
     * @param int $limit Maximum rows to return.
     * @param int $offset Rows to skip.
     * @return list<array<string, mixed>> Entry rows without their body.
     */
    public function published(int $limit = 20, int $offset = 0): array
    {
        // The body is excluded: a list page never renders it, and blog content
        // is stored as MEDIUMTEXT, so selecting it would move far more data than
        // the page uses.
        $statement = $this->database->pdo()->prepare(
            'SELECT b.id, b.title, b.slug, b.excerpt, b.cover_image, b.published_at, b.created_at,
                    u.username, pr.display_name, pr.avatar_path,
                    (SELECT COUNT(*) FROM blog_comments c WHERE c.blog_post_id = b.id AND c.is_deleted = 0) AS comment_count
             FROM blog_posts b
             JOIN users u ON u.id = b.author_id
             LEFT JOIN user_profiles pr ON pr.user_id = b.author_id
             WHERE b.is_published = 1
             ORDER BY b.published_at DESC, b.id DESC
             LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Find one published entry by slug or identifier.
     *
     * @param string $identifier Slug or numeric identifier.
     * @return array<string, mixed>|null The entry, or null when not found.
     */
    public function findPublished(string $identifier): ?array
    {
        // The column name is chosen from this fixed pair, never from the
        // caller's value, so the interpolated identifier cannot be injected.
        $column = ctype_digit($identifier) ? 'id' : 'slug';

        return $this->database->selectOne(
            sprintf(
                'SELECT b.id, b.title, b.slug, b.excerpt, b.content, b.cover_image, b.author_id,
                        b.published_at, b.created_at, b.updated_at,
                        u.username, pr.display_name, pr.avatar_path
                 FROM blog_posts b
                 JOIN users u ON u.id = b.author_id
                 LEFT JOIN user_profiles pr ON pr.user_id = b.author_id
                 WHERE b.%s = :identifier AND b.is_published = 1',
                $column,
            ),
            ['identifier' => $identifier],
        );
    }

    /**
     * Fetch a different entry for the "read next" link.
     *
     * @param int $excludeId Entry to exclude.
     * @return array<string, mixed>|null Another entry, or null when none exist.
     */
    public function randomOther(int $excludeId): ?array
    {
        return $this->database->selectOne(
            'SELECT b.id, b.slug, b.title
             FROM blog_posts b
             WHERE b.is_published = 1 AND b.id != :id
             ORDER BY b.published_at DESC LIMIT 1',
            ['id' => $excludeId],
        );
    }

    /**
     * Create an entry.
     *
     * @param int $authorId Author identifier.
     * @param string $title Entry title.
     * @param string $content Entry body.
     * @param string|null $coverImage Public path of the cover image.
     * @param bool $published Whether the entry is visible immediately.
     * @return int The new entry identifier.
     */
    public function create(int $authorId, string $title, string $content, ?string $coverImage = null, bool $published = true): int
    {
        // Built from the Markdown source rather than from the raw text, so the
        // excerpt cannot contain a heading marker, a code fence, or half a tag.
        $excerpt = Excerpt::from($content, 320);

        return $this->database->insert(
            'INSERT INTO blog_posts (author_id, title, slug, excerpt, content, cover_image, is_published, published_at)
             VALUES (:author_id, :title, :slug, :excerpt, :content, :cover, :published, :published_at)',
            [
                'author_id' => $authorId,
                'title' => $title,
                'slug' => $this->uniqueSlug($title),
                'excerpt' => $excerpt,
                'content' => $content,
                'cover' => $coverImage,
                'published' => $published ? 1 : 0,
                'published_at' => $published ? date('Y-m-d H:i:s') : null,
            ],
        );
    }

    /**
     * Update an entry's editable fields.
     *
     * @param int $id Entry identifier.
     * @param string $title New title.
     * @param string $content New body.
     * @param string|null $coverImage New cover path, or null to clear it.
     * @return bool True when a row was changed.
     */
    public function update(int $id, string $title, string $content, ?string $coverImage = null): bool
    {
        $affected = $this->database->execute(
            'UPDATE blog_posts
             SET title = :title, content = :content, excerpt = :excerpt, cover_image = :cover,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                'id' => $id,
                'title' => $title,
                'content' => $content,
                'excerpt' => Excerpt::from($content, 320),
                'cover' => $coverImage,
            ],
        );

        return $affected > 0;
    }

    /**
     * Remove an entry. Comments and likes follow through the foreign keys.
     *
     * @param int $id Entry identifier.
     * @return bool True when a row was removed.
     */
    public function delete(int $id): bool
    {
        return $this->database->execute('DELETE FROM blog_posts WHERE id = :id', ['id' => $id]) > 0;
    }

    /**
     * List comments on an entry.
     *
     * @param int $blogPostId Entry identifier.
     * @return list<array<string, mixed>> Comment rows.
     */
    public function comments(int $blogPostId): array
    {
        return $this->database->select(
            'SELECT c.id, c.content, c.display_name, c.created_at, c.user_id,
                    pr.avatar_path, u.username
             FROM blog_comments c
             LEFT JOIN users u ON u.id = c.user_id
             LEFT JOIN user_profiles pr ON pr.user_id = c.user_id
             WHERE c.blog_post_id = :post_id AND c.is_deleted = 0
             ORDER BY c.created_at ASC, c.id ASC',
            ['post_id' => $blogPostId],
        );
    }

    /**
     * Add a comment.
     *
     * The display name is captured at write time. A signed-out reader supplies
     * their own, and a signed-in reader has their current display name stored,
     * so rendering never has to join back to a value that may have changed.
     *
     * @param int $blogPostId Entry identifier.
     * @param int|null $userId Commenter identifier, or null for a guest.
     * @param string $displayName Name to render.
     * @param string $content Comment body.
     * @return int The new comment identifier.
     */
    public function addComment(int $blogPostId, ?int $userId, string $displayName, string $content): int
    {
        return $this->database->insert(
            'INSERT INTO blog_comments (blog_post_id, user_id, display_name, content)
             VALUES (:post_id, :user_id, :display_name, :content)',
            [
                'post_id' => $blogPostId,
                'user_id' => $userId,
                'display_name' => mb_substr($displayName, 0, 32),
                'content' => $content,
            ],
        );
    }

    /**
     * Add a like, relying on the composite primary key to reject duplicates.
     *
     * @param int $blogPostId Entry identifier.
     * @param int $userId Reader identifier.
     * @return bool True when a new like was recorded.
     */
    public function like(int $blogPostId, int $userId): bool
    {
        $inserted = $this->database->execute(
            $this->database->driver() === 'sqlite'
                ? 'INSERT INTO blog_likes (blog_post_id, user_id) VALUES (:post_id, :user_id)
                   ON CONFLICT (blog_post_id, user_id) DO NOTHING'
                : 'INSERT IGNORE INTO blog_likes (blog_post_id, user_id) VALUES (:post_id, :user_id)',
            ['post_id' => $blogPostId, 'user_id' => $userId],
        );

        return $inserted > 0;
    }

    /**
     * Remove a like.
     *
     * @param int $blogPostId Entry identifier.
     * @param int $userId Reader identifier.
     * @return bool True when a like was removed.
     */
    public function unlike(int $blogPostId, int $userId): bool
    {
        return $this->database->execute(
            'DELETE FROM blog_likes WHERE blog_post_id = :post_id AND user_id = :user_id',
            ['post_id' => $blogPostId, 'user_id' => $userId],
        ) > 0;
    }

    /**
     * @param int $blogPostId Entry identifier.
     * @param int $userId Reader identifier.
     * @return bool True when the reader has liked the entry.
     */
    public function hasLiked(int $blogPostId, int $userId): bool
    {
        return $this->database->selectOne(
            'SELECT 1 AS liked FROM blog_likes WHERE blog_post_id = :post_id AND user_id = :user_id',
            ['post_id' => $blogPostId, 'user_id' => $userId],
        ) !== null;
    }

    /**
     * @param int $blogPostId Entry identifier.
     * @return int Number of likes.
     */
    public function likeCount(int $blogPostId): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(*) AS total FROM blog_likes WHERE blog_post_id = :post_id',
            ['post_id' => $blogPostId],
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Count published entries, used for pagination.
     *
     * @return int Number of published entries.
     */
    public function publishedCount(): int
    {
        $row = $this->database->selectOne('SELECT COUNT(*) AS total FROM blog_posts WHERE is_published = 1');

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Build a slug that is not already taken.
     *
     * @param string $title Source title.
     * @return string A unique slug.
     */
    private function uniqueSlug(string $title): string
    {
        $base = strtolower(trim((string) preg_replace('/[^\p{Han}\p{Latin}\p{N}]+/u', '-', $title), '-'));
        $base = $base === '' ? 'entry' : mb_substr($base, 0, 140);
        $slug = $base;
        $suffix = 2;

        // The column is unique, so a repeated title must still produce a new row
        // rather than failing on the constraint.
        while ($this->database->selectOne('SELECT id FROM blog_posts WHERE slug = :slug', ['slug' => $slug]) !== null) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
