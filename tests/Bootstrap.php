<?php

declare(strict_types=1);

namespace Tests;

use App\Database\Database;
use App\Database\Migrator;

/**
 * Builds a throwaway database for the test suite.
 *
 * The suite runs against the production schema translated to SQLite, so unique
 * constraints, foreign keys, and check constraints are all exercised exactly as
 * they are in MySQL. Each call returns a fresh in-memory database.
 */
final class Bootstrap
{
    /**
     * Create an empty database with the full schema applied.
     *
     * @return Database A migrated, empty database.
     */
    public static function database(): Database
    {
        $database = Database::boot([
            'driver' => 'sqlite',
            'sqlite_path' => ':memory:',
        ]);

        $migrator = new Migrator(
            $database,
            dirname(__DIR__) . '/database/migrations',
            translateToSqlite: true,
        );
        $migrator->migrate();

        return $database;
    }

    /**
     * Create a migrated database seeded with the reference data the tests need.
     *
     * @return Database A migrated database with users, categories, and shop items.
     */
    public static function seeded(): Database
    {
        $database = self::database();

        $database->execute(
            "INSERT INTO users (id, username, password_hash, role, status)
             VALUES (1, 'alice', :hash, 'user', 'active')",
            ['hash' => password_hash('correct-horse-battery', PASSWORD_DEFAULT)],
        );
        $database->execute(
            "INSERT INTO users (id, username, password_hash, role, status)
             VALUES (2, 'mod', :hash, 'moderator', 'active')",
            ['hash' => password_hash('moderator-pass', PASSWORD_DEFAULT)],
        );
        $database->execute(
            "INSERT INTO users (id, username, password_hash, role, status)
             VALUES (3, 'suspended', :hash, 'user', 'suspended')",
            ['hash' => password_hash('suspended-pass', PASSWORD_DEFAULT)],
        );

        $database->execute(
            "INSERT INTO user_profiles (user_id, display_name, exp, stardust)
             VALUES (1, 'Alice', 0, 100), (2, 'Moderator', 0, 0), (3, 'Suspended', 0, 0)",
        );

        $database->execute(
            "INSERT INTO post_categories (id, slug, name, icon, sort_order)
             VALUES (1, 'daily', '日常吐槽', '☕', 1), (2, 'tech', '代码深空', '💻', 2)",
        );

        $database->execute(
            "INSERT INTO shop_items (id, code, name, description, type, rarity, price, icon, css_class)
             VALUES (1, 'moss', '苍绿之径苔藓', '名字特效', 'effect', 'common', 50, '🌿', 'effect-green-moss'),
                    (2, 'radiance', '辐光', '稀有特效', 'effect', 'legendary', 500, '✨', 'effect-radiance'),
                    (3, 'frame_weaver', '编织者之歌', '头像框', 'avatar_frame', 'rare', 120, '🕸', 'frame-weaver')",
        );

        return $database;
    }
}
