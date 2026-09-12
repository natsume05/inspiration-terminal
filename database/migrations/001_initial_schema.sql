-- ---------------------------------------------------------------------------
-- Inspiration Terminal — initial schema (22 tables)
--
-- Design rules applied throughout:
--   * Every relationship uses an integer foreign key, never a username string.
--   * Uniqueness that the business depends on is enforced by the database,
--     not by application code that can race.
--   * Economy columns are UNSIGNED so a balance can never go negative, even if
--     a code path forgets to check.
--   * Timestamps default to the current time so inserts stay short.
--   * Deletion cascades are explicit: removing a post removes its replies.
-- ---------------------------------------------------------------------------

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(32) NOT NULL,
    email VARCHAR(190) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_profiles (
    user_id INT UNSIGNED NOT NULL,
    display_name VARCHAR(48) NOT NULL,
    bio VARCHAR(255) NOT NULL DEFAULT '',
    avatar_path VARCHAR(255) NOT NULL DEFAULT '',
    custom_title VARCHAR(32) NULL,
    exp INT UNSIGNED NOT NULL DEFAULT 0 CHECK (exp >= 0),
    stardust INT UNSIGNED NOT NULL DEFAULT 0 CHECK (stardust >= 0),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY idx_profiles_exp (exp),
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(32) NOT NULL,
    name VARCHAR(48) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    title VARCHAR(120) NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    like_count INT UNSIGNED NOT NULL DEFAULT 0,
    comment_count INT UNSIGNED NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    deleted_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_posts_category_created (category_id, created_at),
    KEY idx_posts_user_created (user_id, created_at),
    KEY idx_posts_created (created_at),
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_category FOREIGN KEY (category_id) REFERENCES post_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_posts_deleter FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_comments_post_created (post_id, created_at),
    KEY idx_comments_user (user_id),
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The composite primary key is the duplicate-like guard: a second INSERT for
-- the same (user, post) pair fails at the database level, so concurrent
-- double-clicks can no longer inflate a post's like count.
CREATE TABLE post_likes (
    user_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, post_id),
    KEY idx_post_likes_post (post_id),
    CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    author_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    excerpt VARCHAR(320) NOT NULL DEFAULT '',
    content MEDIUMTEXT NOT NULL,
    cover_image VARCHAR(255) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    published_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_blog_slug (slug),
    KEY idx_blog_published (is_published, published_at),
    CONSTRAINT fk_blog_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_id is nullable so guests may comment; display_name always carries the
-- name to render, which removes the old join against a mutable username.
CREATE TABLE blog_comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    blog_post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    display_name VARCHAR(32) NOT NULL,
    content TEXT NOT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_blog_comments_post (blog_post_id, created_at),
    CONSTRAINT fk_blog_comments_post FOREIGN KEY (blog_post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_likes (
    blog_post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blog_post_id, user_id),
    KEY idx_blog_likes_user (user_id),
    CONSTRAINT fk_blog_likes_post FOREIGN KEY (blog_post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(48) NOT NULL,
    name VARCHAR(48) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_post_tags (
    blog_post_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (blog_post_id, tag_id),
    KEY idx_blog_tags_tag (tag_id),
    CONSTRAINT fk_blog_tags_post FOREIGN KEY (blog_post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_blog_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shop_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    type ENUM('effect','avatar_frame','badge') NOT NULL,
    rarity ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
    price INT UNSIGNED NOT NULL DEFAULT 0 CHECK (price >= 0),
    icon VARCHAR(16) NOT NULL DEFAULT '',
    css_class VARCHAR(64) NOT NULL DEFAULT '',
    is_forsale TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_code (code),
    KEY idx_shop_sale (is_forsale, type),
    KEY idx_shop_rarity (rarity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The composite primary key makes double purchasing impossible, so the old
-- "check ownership then insert" race disappears.
CREATE TABLE user_items (
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    is_equipped TINYINT(1) NOT NULL DEFAULT 0,
    acquired_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, item_id),
    KEY idx_user_items_user (user_id),
    CONSTRAINT fk_user_items_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_items_item FOREIGN KEY (item_id) REFERENCES shop_items (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One generic throttle table replaces three per-feature counters: adding a new
-- rate-limited action no longer requires a schema change.
CREATE TABLE rate_limits (
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(32) NOT NULL,
    window_date DATE NOT NULL,
    counter INT UNSIGNED NOT NULL DEFAULT 0 CHECK (counter >= 0),
    PRIMARY KEY (user_id, action, window_date),
    CONSTRAINT fk_rate_limits_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Private notes are stored encrypted (AES-256-GCM). The key is derived from
-- APP_KEY, which is never stored in the database, so a database dump alone
-- cannot reveal note contents.
CREATE TABLE private_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    ciphertext MEDIUMBLOB NOT NULL,
    nonce BINARY(12) NOT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notes_user_created (user_id, created_at),
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    type ENUM('bug','suggestion','question','other') NOT NULL DEFAULT 'other',
    content TEXT NOT NULL,
    status ENUM('pending','reviewing','resolved','rejected') NOT NULL DEFAULT 'pending',
    admin_reply TEXT NULL,
    replied_by INT UNSIGNED NULL,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_status_created (status, created_at),
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_feedback_replier FOREIGN KEY (replied_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('comment','like','reply','system','reward') NOT NULL,
    actor_id INT UNSIGNED NULL,
    target_type VARCHAR(32) NOT NULL DEFAULT '',
    target_id INT UNSIGNED NULL,
    message VARCHAR(255) NOT NULL DEFAULT '',
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_unread (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    content VARCHAR(500) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_announcements_active (is_active, created_at),
    CONSTRAINT fk_announcements_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tools (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(80) NOT NULL,
    url VARCHAR(500) NOT NULL,
    icon VARCHAR(16) NOT NULL DEFAULT '',
    description VARCHAR(255) NOT NULL DEFAULT '',
    category VARCHAR(48) NOT NULL DEFAULT 'general',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tools_category (category, sort_order),
    KEY idx_tools_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE github_projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    remote_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    url VARCHAR(500) NOT NULL,
    stars INT UNSIGNED NOT NULL DEFAULT 0,
    forks INT UNSIGNED NOT NULL DEFAULT 0,
    language VARCHAR(64) NOT NULL DEFAULT '',
    list_type ENUM('trending','all_time') NOT NULL,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_github_remote (remote_id),
    KEY idx_github_list (list_type, stars)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    action VARCHAR(48) NOT NULL,
    target_type VARCHAR(32) NOT NULL DEFAULT '',
    target_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user_created (user_id, created_at),
    KEY idx_audit_action_created (action, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cached third-party API responses. Caching here protects the upstream rate
-- limit and keeps pages usable when the upstream API is unavailable.
CREATE TABLE api_cache (
    cache_key VARCHAR(190) NOT NULL,
    payload MEDIUMTEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cache_key),
    KEY idx_api_cache_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed-login throttle. Keyed by a hash of the client address plus the
-- attempted username rather than a user id, because a failed login has no
-- authenticated user to attach to. Without this, passwords can be brute forced
-- at whatever rate the network allows.
CREATE TABLE login_attempts (
    attempt_key VARCHAR(64) NOT NULL,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0 CHECK (failed_count >= 0),
    first_failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (attempt_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
