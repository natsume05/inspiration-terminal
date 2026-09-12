<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Data access for accounts and their profile fields.
 *
 * Account credentials and profile decoration live in separate tables, so this
 * repository joins them and exposes one row per user to the rest of the app.
 */
final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Find a user by identifier.
     *
     * @param int $userId User identifier.
     * @return array<string, mixed>|null The user row, or null.
     */
    public function find(int $userId): ?array
    {
        return $this->database->selectOne(
            'SELECT u.id, u.username, u.email, u.role, u.status, u.password_hash, u.created_at,
                    p.display_name, p.bio, p.avatar_path, p.custom_title, p.exp, p.stardust
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.id = :id',
            ['id' => $userId],
        );
    }

    /**
     * Find a user by login name.
     *
     * @param string $username The login name.
     * @return array<string, mixed>|null The user row, or null.
     */
    public function findByUsername(string $username): ?array
    {
        return $this->database->selectOne(
            'SELECT u.id, u.username, u.role, u.status, u.password_hash,
                    p.display_name, p.avatar_path, p.custom_title, p.exp, p.stardust
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.username = :username',
            ['username' => $username],
        );
    }

    /**
     * Create an account together with its profile row.
     *
     * Both inserts are wrapped in a transaction so a failure cannot leave an
     * account without a profile, which would break every join downstream.
     *
     * @param string $username Login name.
     * @param string $passwordHash Already hashed password.
     * @param string $displayName Name rendered in the interface.
     * @param string|null $email Optional email address.
     * @param string $role Initial role.
     * @return int The new user identifier.
     */
    public function create(
        string $username,
        string $passwordHash,
        string $displayName,
        ?string $email = null,
        string $role = 'user',
    ): int {
        return $this->database->transaction(function (Database $database) use (
            $username,
            $passwordHash,
            $displayName,
            $email,
            $role,
        ): int {
            $userId = $database->insert(
                'INSERT INTO users (username, email, password_hash, role, status)
                 VALUES (:username, :email, :password_hash, :role, :status)',
                [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $passwordHash,
                    'role' => $role,
                    'status' => 'active',
                ],
            );

            $database->execute(
                'INSERT INTO user_profiles (user_id, display_name) VALUES (:user_id, :display_name)',
                ['user_id' => $userId, 'display_name' => $displayName],
            );

            return $userId;
        });
    }

    /**
     * Check whether a login name is taken.
     *
     * @param string $username Login name to test.
     * @return bool True when the name already exists.
     */
    public function usernameExists(string $username): bool
    {
        return $this->database->selectOne(
            'SELECT id FROM users WHERE username = :username',
            ['username' => $username],
        ) !== null;
    }

    /**
     * Record a successful login.
     *
     * @param int $userId User identifier.
     * @return void
     */
    public function touchLastLogin(int $userId): void
    {
        $this->database->execute(
            'UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $userId],
        );
    }

    /**
     * Replace the stored password hash.
     *
     * @param int $userId User identifier.
     * @param string $passwordHash The new hash.
     * @return void
     */
    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->database->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            ['hash' => $passwordHash, 'id' => $userId],
        );
    }

    /**
     * Update the mutable profile fields.
     *
     * @param int $userId User identifier.
     * @param array<string, mixed> $fields Values to change.
     * @return void
     */
    public function updateProfile(int $userId, array $fields): void
    {
        $allowed = ['display_name', 'bio', 'avatar_path'];
        $assignments = [];
        $parameters = ['user_id' => $userId];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $fields)) {
                continue;
            }

            $assignments[] = sprintf('%s = :%s', $column, $column);
            $parameters[$column] = $fields[$column];
        }

        if ($assignments === []) {
            return;
        }

        $this->database->execute(
            sprintf(
                'UPDATE user_profiles SET %s, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id',
                implode(', ', $assignments),
            ),
            $parameters,
        );
    }

    /**
     * Change the login name.
     *
     * Safe to do now that every relationship references the numeric id, so
     * historic posts and comments keep pointing at the same account.
     *
     * @param int $userId User identifier.
     * @param string $username The new login name.
     * @return void
     */
    public function updateUsername(int $userId, string $username): void
    {
        $this->database->execute(
            'UPDATE users SET username = :username WHERE id = :id',
            ['username' => $username, 'id' => $userId],
        );
    }

    /**
     * List the identifiers of every active account.
     *
     * Used to fan a site-wide announcement out to notifications. Suspended
     * accounts are excluded: they cannot sign in, so a notification for them
     * would never be read.
     *
     * @return list<int> Active account identifiers.
     */
    public function activeUserIds(): array
    {
        $rows = $this->database->select("SELECT id FROM users WHERE status = 'active' ORDER BY id ASC");

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param int $userId User identifier.
     * @return int Current stardust balance.
     */
    public function balance(int $userId): int
    {
        $row = $this->database->selectOne(
            'SELECT stardust FROM user_profiles WHERE user_id = :id',
            ['id' => $userId],
        );

        return (int) ($row['stardust'] ?? 0);
    }

    /**
     * @param int $userId User identifier.
     * @return int Current experience total.
     */
    public function experience(int $userId): int
    {
        $row = $this->database->selectOne(
            'SELECT exp FROM user_profiles WHERE user_id = :id',
            ['id' => $userId],
        );

        return (int) ($row['exp'] ?? 0);
    }
}
