<?php

declare(strict_types=1);

namespace App\Database;

use App\Security\Crypto;
use PDO;
use Throwable;

/**
 * Copies data from the legacy `my_forum` schema into the current one.
 *
 * The legacy schema differs in ways that make a straight copy impossible:
 *
 *   * posts and blog comments reference their author by **username string**, and
 *     at least one post names an author that no longer exists;
 *   * some rows carry the zero date `0000-00-00 00:00:00`, which the strict SQL
 *     mode this application requires rejects outright;
 *   * the economy stored one counter column per activity instead of a generic
 *     table;
 *   * private notes were stored as plaintext.
 *
 * Each of those is handled explicitly rather than being allowed to abort the
 * run, and every step is idempotent so the migration can be re-run.
 */
final class LegacyMigrator
{
    /** @var array<string, mixed> */
    private array $report = [
        'users' => 0,
        'placeholder_users' => 0,
        'categories' => 0,
        'posts' => 0,
        'posts_with_unknown_author' => 0,
        'posts_with_zero_date' => 0,
        'comments' => 0,
        'likes' => 0,
        'likes_deduplicated' => 0,
        'blog_posts' => 0,
        'tools' => 0,
        'shop_items' => 0,
        'inventory' => 0,
        'inventory_deduplicated' => 0,
        'private_notes' => 0,
        'notes_encrypted' => 0,
        'feedback' => 0,
    ];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param PDO $source Connection to the legacy database.
     * @param Database $target Connection to the current database.
     * @param Crypto|null $crypto Used to encrypt legacy plaintext notes.
     */
    public function __construct(
        private readonly PDO $source,
        private readonly Database $target,
        private readonly ?Crypto $crypto = null,
    ) {
    }

    /**
     * @return array<string, mixed> Counts of everything that was migrated.
     */
    public function report(): array
    {
        return $this->report;
    }

    /**
     * @return list<string> Non-fatal problems encountered during the run.
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Run every migration step.
     *
     * @return void
     */
    public function migrate(): void
    {
        $this->migrateUsers();
        $this->migrateCategories();
        $this->migratePosts();
        $this->migrateComments();
        $this->migrateLikes();
        $this->migrateBlogPosts();
        $this->migrateTools();
        $this->migrateShopItems();
        $this->migrateInventory();
        $this->migratePrivateNotes();
        $this->migrateFeedback();
    }

    /**
     * Copy accounts, preserving the stored password hash so existing users keep
     * their passwords, and profile values so balances survive.
     *
     * @return void
     */
    private function migrateUsers(): void
    {
        foreach ($this->rows('SELECT * FROM users') as $row) {
            $id = (int) $row['id'];
            $username = (string) $row['username'];

            $this->target->execute(
                'INSERT INTO users (id, username, email, password_hash, role, status, created_at)
                 VALUES (:id, :username, :email, :hash, :role, :status, :created_at)
                 ON DUPLICATE KEY UPDATE username = VALUES(username), password_hash = VALUES(password_hash)',
                [
                    'id' => $id,
                    'username' => $username,
                    // Legacy emails were frequently blank or duplicated; the
                    // column is unique, so only a plausible address is carried over.
                    'email' => $this->plausibleEmail($row['email'] ?? null, $id),
                    'hash' => (string) ($row['password'] ?? ''),
                    'role' => $this->validRole($row['role'] ?? null),
                    'status' => 'active',
                    'created_at' => $this->validDate($row['created_at'] ?? null),
                ],
            );

            $this->target->execute(
                'INSERT INTO user_profiles (user_id, display_name, bio, avatar_path, custom_title, exp, stardust)
                 VALUES (:id, :display_name, :bio, :avatar, :title, :exp, :stardust)
                 ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), exp = VALUES(exp), stardust = VALUES(stardust)',
                [
                    'id' => $id,
                    'display_name' => $username,
                    'bio' => mb_substr((string) ($row['bio'] ?? ''), 0, 255),
                    'avatar' => (string) ($row['avatar'] ?? ''),
                    'title' => ($row['custom_title'] ?? null) === null ? null : mb_substr((string) $row['custom_title'], 0, 32),
                    'exp' => max(0, (int) ($row['exp'] ?? 0)),
                    'stardust' => max(0, (int) ($row['stardust'] ?? 0)),
                ],
            );

            $this->report['users']++;
        }

        // The legacy ENUM permitted an empty-string value, which non-strict mode
        // stored happily. It is not a role the new schema allows, and it would
        // leave isModerator()/isAdmin() comparing against a meaningless value.
        $repaired = $this->target->execute(
            "UPDATE users SET role = 'user' WHERE role NOT IN ('user', 'moderator', 'admin')",
        );

        if ($repaired > 0) {
            $this->warnings[] = sprintf('%d account(s) had an invalid role; reset to "user"', $repaired);
        }
    }

    /**
     * Map legacy post tags onto the current category rows.
     *
     * @return void
     */
    private function migrateCategories(): void
    {
        $categories = [
            'daily' => ['日常吐槽', '☕', 1],
            'game' => ['游戏圣殿', '🎮', 2],
            'tech' => ['代码深空', '💻', 3],
            'void' => ['虚空回响', '🌌', 4],
        ];

        foreach ($categories as $slug => [$name, $icon, $order]) {
            $this->target->execute(
                'INSERT INTO post_categories (slug, name, icon, sort_order)
                 VALUES (:slug, :name, :icon, :sort_order)
                 ON DUPLICATE KEY UPDATE name = VALUES(name)',
                ['slug' => $slug, 'name' => $name, 'icon' => $icon, 'sort_order' => $order],
            );

            $this->report['categories']++;
        }
    }

    /**
     * Copy posts, resolving each author name to a real account.
     *
     * A post whose author has no account would violate the new foreign key.
     * Rather than dropping the content, a clearly-labelled placeholder account is
     * created and the post is reattributed to it, with a warning recorded so the
     * decision is visible rather than silent.
     *
     * @return void
     */
    private function migratePosts(): void
    {
        $categoryIds = [];
        foreach ($this->target->select('SELECT id, slug FROM post_categories') as $row) {
            $categoryIds[(string) $row['slug']] = (int) $row['id'];
        }

        $usersByName = $this->usernameIndex();

        foreach ($this->rows('SELECT * FROM posts') as $row) {
            $postId = (int) $row['id'];
            $author = trim((string) ($row['author'] ?? ''));

            $userId = $usersByName[mb_strtolower($author, 'UTF-8')] ?? null;

            if ($userId === null) {
                $userId = $this->placeholderUserFor($author);
                $this->report['posts_with_unknown_author']++;
                $this->warnings[] = sprintf(
                    'post #%d: author "%s" had no account; reattributed to placeholder account',
                    $postId,
                    $author === '' ? '(empty)' : $author,
                );
            }

            // A zero date cannot be stored once strict mode is on.
            $createdAt = $this->validDate($row['created_at'] ?? null);
            if ($createdAt !== (string) ($row['created_at'] ?? '')) {
                $this->report['posts_with_zero_date']++;
            }

            $slug = (string) ($row['tag'] ?? 'daily');
            $categoryId = $categoryIds[$slug] ?? $categoryIds['daily'] ?? null;

            $this->target->execute(
                'INSERT INTO posts (id, user_id, category_id, title, content, image_path, created_at)
                 VALUES (:id, :user_id, :category_id, :title, :content, :image, :created_at)
                 ON DUPLICATE KEY UPDATE content = VALUES(content)',
                [
                    'id' => $postId,
                    'user_id' => $userId,
                    'category_id' => $categoryId,
                    'title' => '',
                    'content' => (string) ($row['content'] ?? ''),
                    'image' => $this->nullIfBlank($row['image'] ?? null),
                    'created_at' => $createdAt,
                ],
            );

            $this->report['posts']++;
        }
    }

    /**
     * Copy comments, resolving their authors the same way as posts.
     *
     * @return void
     */
    private function migrateComments(): void
    {
        $usersByName = $this->usernameIndex();

        foreach ($this->rows('SELECT * FROM comments') as $row) {
            $postId = (int) $row['post_id'];
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId <= 0 || !isset($usersByName['__by_id'][$userId])) {
                $userId = $this->placeholderUserFor('unknown');
                $this->warnings[] = sprintf('comment #%d: missing author; reattributed', (int) $row['id']);
            }

            // Skip comments whose post did not survive the post migration.
            $post = $this->target->selectOne('SELECT id FROM posts WHERE id = :id', ['id' => $postId]);
            if ($post === null) {
                $this->warnings[] = sprintf('comment #%d skipped: post #%d does not exist', (int) $row['id'], $postId);

                continue;
            }

            $this->target->execute(
                'INSERT INTO comments (id, post_id, user_id, content, created_at)
                 VALUES (:id, :post_id, :user_id, :content, :created_at)
                 ON DUPLICATE KEY UPDATE content = VALUES(content)',
                [
                    'id' => (int) $row['id'],
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'content' => (string) ($row['content'] ?? ''),
                    'created_at' => $this->validDate($row['created_at'] ?? null),
                ],
            );

            $this->report['comments']++;
        }
    }

    /**
     * Copy likes and recompute the denormalised counters.
     *
     * The legacy table had no uniqueness guarantee, so duplicates are collapsed
     * here; the counters are then derived from the rows that actually exist,
     * which is the only way to be sure they agree.
     *
     * @return void
     */
    private function migrateLikes(): void
    {
        $seen = [];

        foreach ($this->rows('SELECT * FROM likes') as $row) {
            $postId = (int) $row['post_id'];
            $userId = (int) $row['user_id'];

            $post = $this->target->selectOne('SELECT id FROM posts WHERE id = :id', ['id' => $postId]);
            $user = $this->target->selectOne('SELECT id FROM users WHERE id = :id', ['id' => $userId]);

            if ($post === null || $user === null) {
                $this->warnings[] = sprintf('like (#%d) skipped: missing post or user', (int) $row['id']);

                continue;
            }

            $key = $postId . ':' . $userId;
            if (isset($seen[$key])) {
                $this->report['likes_deduplicated']++;

                continue;
            }
            $seen[$key] = true;

            $this->target->execute(
                'INSERT IGNORE INTO post_likes (post_id, user_id, created_at)
                 VALUES (:post_id, :user_id, :created_at)',
                [
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'created_at' => $this->validDate($row['created_at'] ?? null),
                ],
            );

            $this->report['likes']++;
        }

        // Recompute counters from the migrated rows so the page cannot disagree
        // with the data behind it.
        $this->target->execute(
            'UPDATE posts p SET like_count = (
                SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id
             )',
        );
        $this->target->execute(
            'UPDATE posts p SET comment_count = (
                SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.is_deleted = 0
             )',
        );
    }

    /**
     * Copy blog posts, generating the slug the new schema requires.
     *
     * @return void
     */
    private function migrateBlogPosts(): void
    {
        foreach ($this->rows('SELECT * FROM blog_posts') as $row) {
            $id = (int) $row['id'];
            $title = (string) ($row['title'] ?? '未命名日志');

            $this->target->execute(
                'INSERT INTO blog_posts (id, author_id, title, slug, excerpt, content, cover_image, is_published, published_at, created_at)
                 VALUES (:id, :author_id, :title, :slug, :excerpt, :content, :cover, 1, :published, :created_at)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content)',
                [
                    'id' => $id,
                    // Blog posts were authored by the site owner.
                    'author_id' => $this->ownerId(),
                    'title' => mb_substr($title, 0, 160),
                    'slug' => $this->slugify($title, $id),
                    'excerpt' => mb_substr(trim(strip_tags((string) ($row['content'] ?? ''))), 0, 320),
                    'content' => (string) ($row['content'] ?? ''),
                    'cover' => $this->nullIfBlank($row['cover_image'] ?? null),
                    'published' => $this->validDate($row['created_at'] ?? null),
                    'created_at' => $this->validDate($row['created_at'] ?? null),
                ],
            );

            $this->report['blog_posts']++;
        }
    }

    /**
     * Copy toolbox links.
     *
     * @return void
     */
    private function migrateTools(): void
    {
        foreach ($this->rows('SELECT * FROM tools') as $row) {
            $url = (string) ($row['url'] ?? '');

            if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->warnings[] = sprintf('tool #%d skipped: invalid URL', (int) $row['id']);

                continue;
            }

            $this->target->execute(
                'INSERT INTO tools (id, title, url, icon, description, category, sort_order)
                 VALUES (:id, :title, :url, :icon, :description, :category, :sort_order)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), url = VALUES(url)',
                [
                    'id' => (int) $row['id'],
                    'title' => mb_substr((string) ($row['title'] ?? $url), 0, 80),
                    'url' => mb_substr($url, 0, 500),
                    'icon' => mb_substr((string) ($row['icon'] ?? ''), 0, 16),
                    'description' => mb_substr((string) ($row['description'] ?? ''), 0, 255),
                    'category' => mb_substr((string) ($row['category'] ?? 'general'), 0, 48),
                    'sort_order' => 0,
                ],
            );

            $this->report['tools']++;
        }
    }

    /**
     * Copy shop items, deriving the code and CSS class the new schema requires.
     *
     * @return void
     */
    private function migrateShopItems(): void
    {
        foreach ($this->rows('SELECT * FROM shop_items') as $row) {
            $id = (int) $row['id'];
            $name = (string) ($row['name'] ?? ('item-' . $id));

            $type = in_array((string) ($row['type'] ?? ''), ['effect', 'avatar_frame', 'badge'], true)
                ? (string) $row['type']
                : 'effect';

            $rarity = in_array((string) ($row['rarity'] ?? ''), ['common', 'rare', 'epic', 'legendary'], true)
                ? (string) $row['rarity']
                : 'common';

            $this->target->execute(
                'INSERT INTO shop_items (id, code, name, description, type, rarity, price, icon, css_class, is_forsale)
                 VALUES (:id, :code, :name, :description, :type, :rarity, :price, :icon, :css_class, :forsale)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price)',
                [
                    'id' => $id,
                    'code' => 'legacy-' . $id,
                    'name' => mb_substr($name, 0, 64),
                    'description' => mb_substr((string) ($row['description'] ?? ''), 0, 255),
                    'type' => $type,
                    'rarity' => $rarity,
                    'price' => max(0, (int) ($row['price'] ?? 0)),
                    'icon' => mb_substr((string) ($row['icon'] ?? ''), 0, 16),
                    'css_class' => mb_substr((string) ($row['effect'] ?? ''), 0, 64),
                    'forsale' => (int) ($row['is_forsale'] ?? 1) === 1 ? 1 : 0,
                ],
            );

            $this->report['shop_items']++;
        }
    }

    /**
     * Copy owned items, collapsing duplicates.
     *
     * @return void
     */
    private function migrateInventory(): void
    {
        $seen = [];

        foreach ($this->rows('SELECT * FROM user_inventory') as $row) {
            $userId = (int) $row['user_id'];
            $itemId = (int) $row['item_id'];

            $user = $this->target->selectOne('SELECT id FROM users WHERE id = :id', ['id' => $userId]);
            $item = $this->target->selectOne('SELECT id FROM shop_items WHERE id = :id', ['id' => $itemId]);

            if ($user === null || $item === null) {
                $this->warnings[] = sprintf('inventory row skipped: user %d or item %d missing', $userId, $itemId);

                continue;
            }

            $key = $userId . ':' . $itemId;
            if (isset($seen[$key])) {
                $this->report['inventory_deduplicated']++;

                continue;
            }
            $seen[$key] = true;

            // Only one item of each type may be equipped; the legacy data could
            // mark several, so the flag is not carried over blindly.
            $this->target->execute(
                'INSERT IGNORE INTO user_items (user_id, item_id, is_equipped) VALUES (:user_id, :item_id, 0)',
                ['user_id' => $userId, 'item_id' => $itemId],
            );

            $this->report['inventory']++;
        }
    }

    /**
     * Copy private notes, encrypting the legacy plaintext.
     *
     * @return void
     */
    private function migratePrivateNotes(): void
    {
        foreach ($this->rows('SELECT * FROM private_notes') as $row) {
            $content = (string) ($row['content'] ?? '');

            if ($content === '') {
                continue;
            }

            if ($this->crypto === null) {
                $this->warnings[] = sprintf(
                    'note #%d skipped: APP_KEY is not configured, so it cannot be encrypted',
                    (int) $row['id'],
                );

                continue;
            }

            $userId = (int) $row['user_id'];
            $user = $this->target->selectOne('SELECT id FROM users WHERE id = :id', ['id' => $userId]);

            if ($user === null) {
                $this->warnings[] = sprintf('note #%d skipped: user %d does not exist', (int) $row['id'], $userId);

                continue;
            }

            $sealed = $this->crypto->encrypt($content);

            $this->target->execute(
                'INSERT INTO private_notes (id, user_id, ciphertext, nonce, created_at)
                 VALUES (:id, :user_id, :ciphertext, :nonce, :created_at)
                 ON DUPLICATE KEY UPDATE ciphertext = VALUES(ciphertext)',
                [
                    'id' => (int) $row['id'],
                    'user_id' => $userId,
                    'ciphertext' => $sealed['ciphertext'],
                    'nonce' => $sealed['nonce'],
                    'created_at' => $this->validDate($row['created_at'] ?? null),
                ],
            );

            $this->report['private_notes']++;
            $this->report['notes_encrypted']++;
        }
    }

    /**
     * Copy feedback submissions.
     *
     * @return void
     */
    private function migrateFeedback(): void
    {
        foreach ($this->rows('SELECT * FROM feedback') as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $user = $userId > 0
                ? $this->target->selectOne('SELECT id FROM users WHERE id = :id', ['id' => $userId])
                : null;

            $status = in_array((string) ($row['status'] ?? ''), ['pending', 'reviewing', 'resolved', 'rejected'], true)
                ? (string) $row['status']
                : 'pending';

            $this->target->execute(
                'INSERT INTO feedback (id, user_id, type, content, status, admin_reply, created_at)
                 VALUES (:id, :user_id, :type, :content, :status, :reply, :created_at)
                 ON DUPLICATE KEY UPDATE content = VALUES(content)',
                [
                    'id' => (int) $row['id'],
                    'user_id' => $user === null ? null : (int) $user['id'],
                    'type' => 'other',
                    'content' => (string) ($row['content'] ?? ''),
                    'status' => $status,
                    'reply' => $this->nullIfBlank($row['admin_reply'] ?? null),
                    'created_at' => $this->validDate($row['created_at'] ?? null),
                ],
            );

            $this->report['feedback']++;
        }
    }

    /**
     * Read every row from a legacy table.
     *
     * @param string $sql Query to run against the legacy connection.
     * @return list<array<string, mixed>> Rows.
     */
    private function rows(string $sql): array
    {
        try {
            $statement = $this->source->query($sql);
        } catch (Throwable $throwable) {
            // A table that does not exist in this legacy installation is not a
            // failure; the migration simply has nothing to copy from it.
            $this->warnings[] = sprintf('skipped query: %s', $throwable->getMessage());

            return [];
        }

        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * Build a lower-case username to identifier index, plus an id index.
     *
     * @return array<string, mixed> Lookup table.
     */
    private function usernameIndex(): array
    {
        $index = ['__by_id' => []];

        foreach ($this->target->select('SELECT id, username FROM users') as $row) {
            $index[mb_strtolower((string) $row['username'], 'UTF-8')] = (int) $row['id'];
            $index['__by_id'][(int) $row['id']] = true;
        }

        return $index;
    }

    /**
     * Return the identifier of a placeholder account holding orphaned content.
     *
     * Creating a labelled account keeps the content rather than discarding it,
     * and keeps every post reachable through a real foreign key.
     *
     * @param string $originalName The author name that could not be resolved.
     * @return int The placeholder account identifier.
     */
    private function placeholderUserFor(string $originalName): int
    {
        $username = 'legacy_archive';

        $existing = $this->target->selectOne('SELECT id FROM users WHERE username = :username', ['username' => $username]);

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        // The password hash is deliberately unusable: this account exists to own
        // orphaned content, and must never be signable into.
        $id = $this->target->insert(
            "INSERT INTO users (username, password_hash, role, status)
             VALUES (:username, :hash, 'user', 'suspended')",
            ['username' => $username, 'hash' => '*'],
        );

        $this->target->execute(
            'INSERT INTO user_profiles (user_id, display_name, bio)
             VALUES (:id, :display_name, :bio)',
            [
                'id' => $id,
                'display_name' => '遗留内容',
                'bio' => mb_substr('自动创建的占位账号，用于承接作者账号已不存在的历史内容。原署名：' . $originalName, 0, 255),
            ],
        );

        $this->report['placeholder_users']++;

        return $id;
    }

    /**
     * Find the site owner, used as the author of migrated blog posts.
     *
     * @return int The owner identifier.
     */
    private function ownerId(): int
    {
        $admin = $this->target->selectOne("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");

        if ($admin !== null) {
            return (int) $admin['id'];
        }

        return $this->placeholderUserFor('site owner');
    }

    /**
     * Replace a zero or unparsable date with the current time.
     *
     * The legacy data contains `0000-00-00 00:00:00`, which strict mode rejects.
     *
     * @param mixed $value Raw date value.
     * @return string A date that is safe to insert.
     */
    private function validDate(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Constrain a legacy role to a value the new ENUM accepts.
     *
     * Under strict mode an unknown value is an error rather than a silent empty
     * string, so it must be normalised here.
     *
     * @param mixed $role Raw role value.
     * @return string A permitted role.
     */
    private function validRole(mixed $role): string
    {
        $role = strtolower(trim((string) $role));

        return in_array($role, ['user', 'moderator', 'admin'], true) ? $role : 'user';
    }

    /**
     * Accept an email only when it is a valid, unique-looking address.
     *
     * The destination column is unique, and legacy values were often blank or
     * repeated, so anything questionable becomes null.
     *
     * @param mixed $email Raw email value.
     * @param int $userId Owner identifier, used to keep the address distinct.
     * @return string|null A usable address, or null.
     */
    private function plausibleEmail(mixed $email, int $userId): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $taken = $this->target->selectOne(
            'SELECT id FROM users WHERE email = :email AND id != :id',
            ['email' => $email, 'id' => $userId],
        );

        return $taken === null ? $email : null;
    }

    /**
     * Build a unique slug from a title.
     *
     * @param string $title Source title.
     * @param int $id Row identifier, appended when the title has no usable text.
     * @return string A slug within the column width.
     */
    private function slugify(string $title, int $id): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^\p{Han}\p{Latin}\p{N}]+/u', '-', $title), '-'));
        $slug = $slug === '' ? 'post-' . $id : $slug;

        return mb_substr($slug, 0, 150) . '-' . $id;
    }

    /**
     * @param mixed $value Candidate value.
     * @return string|null The trimmed value, or null when empty.
     */
    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
