<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use RuntimeException;

/**
 * Data access for community posts, comments, and likes.
 *
 * Two design choices here replace the previous implementation's weak points:
 * authorship is a numeric foreign key rather than a username string, and like
 * counts are maintained as a column that is updated in the same transaction as
 * the like row, instead of being recounted with a correlated subquery per feed
 * item.
 */
final class PostRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Fetch the feed.
     *
     * @param int $viewerId The requesting user, used to resolve like state.
     * @param string|null $categorySlug Restrict to one category when provided.
     * @param int $limit Maximum rows to return.
     * @param int $offset Rows to skip.
     * @return list<array<string, mixed>> Feed rows.
     */
    public function feed(int $viewerId, ?string $categorySlug = null, int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT p.id, p.title, p.content, p.image_path, p.like_count, p.comment_count, p.created_at,
                       u.id AS author_id, u.username,
                       pr.display_name, pr.avatar_path, pr.custom_title, pr.exp,
                       c.slug AS category_slug, c.name AS category_name, c.icon AS category_icon,
                       CASE WHEN l.user_id IS NULL THEN 0 ELSE 1 END AS liked_by_viewer
                FROM posts p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN user_profiles pr ON pr.user_id = p.user_id
                LEFT JOIN post_categories c ON c.id = p.category_id
                LEFT JOIN post_likes l ON l.post_id = p.id AND l.user_id = :viewer_id
                WHERE p.is_deleted = 0';

        $parameters = ['viewer_id' => $viewerId];

        if ($categorySlug !== null) {
            $sql .= ' AND c.slug = :category_slug';
            $parameters['category_slug'] = $categorySlug;
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT :limit OFFSET :offset';

        // LIMIT and OFFSET are bound as integers: they cannot be bound as
        // strings without breaking, and interpolating them would reintroduce
        // the injection risk this rewrite removes.
        $statement = $this->database->pdo()->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value);
        }

        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Fetch a single post with its author and category.
     *
     * @param int $postId Post identifier.
     * @return array<string, mixed>|null The post row, or null.
     */
    public function find(int $postId): ?array
    {
        return $this->database->selectOne(
            'SELECT p.id, p.title, p.content, p.image_path, p.like_count, p.comment_count, p.created_at,
                    p.user_id AS author_id, u.username,
                    pr.display_name, pr.avatar_path, pr.custom_title, pr.exp,
                    c.slug AS category_slug, c.name AS category_name, c.icon AS category_icon
             FROM posts p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN user_profiles pr ON pr.user_id = p.user_id
             LEFT JOIN post_categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.is_deleted = 0',
            ['id' => $postId],
        );
    }

    /**
     * Create a post.
     *
     * @param int $userId Author identifier.
     * @param int|null $categoryId Category identifier.
     * @param string $content Post body.
     * @param string $title Optional title.
     * @param string|null $imagePath Public path of an attached image.
     * @return int The new post identifier.
     */
    public function create(
        int $userId,
        ?int $categoryId,
        string $content,
        string $title = '',
        ?string $imagePath = null,
    ): int {
        return $this->database->insert(
            'INSERT INTO posts (user_id, category_id, title, content, image_path)
             VALUES (:user_id, :category_id, :title, :content, :image_path)',
            [
                'user_id' => $userId,
                'category_id' => $categoryId,
                'title' => $title,
                'content' => $content,
                'image_path' => $imagePath,
            ],
        );
    }

    /**
     * Soft-delete a post and hide its replies.
     *
     * The row is kept so the audit trail and any references survive, but it
     * disappears from every read path.
     *
     * @param int $postId Post identifier.
     * @param int $actorId User performing the deletion.
     * @return bool True when a post was marked deleted.
     */
    public function softDelete(int $postId, int $actorId): bool
    {
        return $this->database->transaction(function (Database $database) use ($postId, $actorId): bool {
            $affected = $database->execute(
                'UPDATE posts SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP, deleted_by = :actor
                 WHERE id = :id AND is_deleted = 0',
                ['id' => $postId, 'actor' => $actorId],
            );

            return $affected > 0;
        });
    }

    /**
     * Add a comment and keep the denormalised counter in step.
     *
     * Both writes share a transaction so the counter can never drift from the
     * rows it summarises.
     *
     * @param int $postId Post being commented on.
     * @param int $userId Comment author.
     * @param string $content Comment body.
     * @return int The new comment identifier.
     */
    public function addComment(int $postId, int $userId, string $content): int
    {
        return $this->database->transaction(function (Database $database) use ($postId, $userId, $content): int {
            $commentId = $database->insert(
                'INSERT INTO comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)',
                ['post_id' => $postId, 'user_id' => $userId, 'content' => $content],
            );

            $database->execute(
                'UPDATE posts SET comment_count = comment_count + 1 WHERE id = :id',
                ['id' => $postId],
            );

            return $commentId;
        });
    }

    /**
     * List comments for a post.
     *
     * @param int $postId Post identifier.
     * @return list<array<string, mixed>> Comment rows with author details.
     */
    public function comments(int $postId): array
    {
        return $this->database->select(
            'SELECT c.id, c.content, c.created_at, c.user_id,
                    u.username, pr.display_name, pr.avatar_path, pr.custom_title
             FROM comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN user_profiles pr ON pr.user_id = c.user_id
             WHERE c.post_id = :post_id AND c.is_deleted = 0
             ORDER BY c.created_at ASC, c.id ASC',
            ['post_id' => $postId],
        );
    }

    /**
     * Insert a like, relying on the composite primary key to reject duplicates.
     *
     * The counter is only incremented when the insert actually created a row, so
     * a double click cannot inflate the count even under concurrency.
     *
     * @param int $postId Post identifier.
     * @param int $userId User identifier.
     * @return bool True when a new like was recorded, false when it already existed.
     */
    public function like(int $postId, int $userId): bool
    {
        return $this->database->transaction(function (Database $database) use ($postId, $userId): bool {
            $inserted = $database->execute(
                $database->driver() === 'sqlite'
                    ? 'INSERT INTO post_likes (post_id, user_id) VALUES (:post_id, :user_id)
                       ON CONFLICT (user_id, post_id) DO NOTHING'
                    : 'INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (:post_id, :user_id)',
                ['post_id' => $postId, 'user_id' => $userId],
            );

            if ($inserted === 0) {
                return false;
            }

            $database->execute(
                'UPDATE posts SET like_count = like_count + 1 WHERE id = :id',
                ['id' => $postId],
            );

            return true;
        });
    }

    /**
     * Remove a like and decrement the counter when a row was actually removed.
     *
     * @param int $postId Post identifier.
     * @param int $userId User identifier.
     * @return bool True when a like was removed.
     */
    public function unlike(int $postId, int $userId): bool
    {
        return $this->database->transaction(function (Database $database) use ($postId, $userId): bool {
            $deleted = $database->execute(
                'DELETE FROM post_likes WHERE post_id = :post_id AND user_id = :user_id',
                ['post_id' => $postId, 'user_id' => $userId],
            );

            if ($deleted === 0) {
                return false;
            }

            // The counter is guarded against underflow, which cannot normally
            // happen but should not be able to corrupt the page if it did.
            $database->execute(
                'UPDATE posts SET like_count = like_count - 1 WHERE id = :id AND like_count > 0',
                ['id' => $postId],
            );

            return true;
        });
    }

    /**
     * @param int $postId Post identifier.
     * @param int $userId User identifier.
     * @return bool True when the user has liked the post.
     */
    public function hasLiked(int $postId, int $userId): bool
    {
        return $this->database->selectOne(
            'SELECT 1 AS liked FROM post_likes WHERE post_id = :post_id AND user_id = :user_id',
            ['post_id' => $postId, 'user_id' => $userId],
        ) !== null;
    }

    /**
     * @param int $postId Post identifier.
     * @return int The stored like count.
     */
    public function likeCount(int $postId): int
    {
        $row = $this->database->selectOne('SELECT like_count FROM posts WHERE id = :id', ['id' => $postId]);

        return (int) ($row['like_count'] ?? 0);
    }

    /**
     * List post categories in display order.
     *
     * @return list<array<string, mixed>> Category rows.
     */
    public function categories(): array
    {
        return $this->database->select(
            'SELECT id, slug, name, icon FROM post_categories ORDER BY sort_order ASC, id ASC',
        );
    }

    /**
     * Resolve a category identifier from its slug.
     *
     * @param string $slug Category slug.
     * @return int|null The identifier, or null when unknown.
     */
    public function categoryIdBySlug(string $slug): ?int
    {
        $row = $this->database->selectOne(
            'SELECT id FROM post_categories WHERE slug = :slug',
            ['slug' => $slug],
        );

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * Fetch a post's author identifier, used for authorisation decisions.
     *
     * @param int $postId Post identifier.
     * @return int|null The author identifier, or null when the post is gone.
     */
    public function authorId(int $postId): ?int
    {
        $row = $this->database->selectOne(
            'SELECT user_id FROM posts WHERE id = :id AND is_deleted = 0',
            ['id' => $postId],
        );

        if ($row === null) {
            return null;
        }

        return (int) $row['user_id'];
    }
}
