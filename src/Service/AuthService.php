<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\Database;
use App\Repository\UserRepository;
use App\Security\Csrf;
use App\Security\Session;

/**
 * Account registration, login, and logout.
 *
 * Hardening applied here that the previous implementation lacked:
 *   * a fixed-cost password comparison even when the account does not exist,
 *     so response timing does not reveal which usernames are registered;
 *   * a failed-login throttle, without which passwords could be brute forced;
 *   * session id regeneration on login, defeating session fixation;
 *   * refusal to authenticate suspended accounts.
 */
final class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900;

    /** A valid bcrypt hash used to burn the same CPU time for unknown users. */
    private const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly Session $session,
        private readonly Csrf $csrf,
    ) {
    }

    /**
     * Create an account and return its identifier.
     *
     * @param string $username Login name.
     * @param string $password Plaintext password.
     * @param string $displayName Name shown in the interface.
     * @param string|null $email Optional email address.
     * @return int The new user identifier.
     */
    public function register(string $username, string $password, string $displayName, ?string $email = null): int
    {
        // PASSWORD_DEFAULT currently selects bcrypt, which salts each hash
        // automatically and is deliberately slow to compute.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        return $this->users->create($username, $hash, $displayName, $email);
    }

    /**
     * Attempt to authenticate a user.
     *
     * @param string $username Login name.
     * @param string $password Plaintext password.
     * @param string $clientKey Value identifying the client, used for throttling.
     * @return array{ok: bool, message: string, user_id?: int}
     */
    public function attempt(string $username, string $password, string $clientKey): array
    {
        $attemptKey = $this->attemptKey($clientKey, $username);

        if ($this->isLockedOut($attemptKey)) {
            return [
                'ok' => false,
                'message' => '尝试次数过多，请稍后再试。',
            ];
        }

        $user = $this->users->findByUsername($username);

        // Always run a verification, even for an unknown account, so the
        // response time cannot be used to enumerate valid usernames.
        $hash = is_array($user) ? (string) $user['password_hash'] : self::DUMMY_HASH;
        $verified = password_verify($password, $hash);

        if (!is_array($user) || !$verified) {
            $this->recordFailure($attemptKey);

            // A single message for both cases: revealing which part was wrong
            // would confirm whether an account exists.
            return ['ok' => false, 'message' => '代号或密钥错误。'];
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return ['ok' => false, 'message' => '该账号已被停用。'];
        }

        $this->clearFailures($attemptKey);
        $this->startSession((int) $user['id'], $user);

        return ['ok' => true, 'message' => '登录成功。', 'user_id' => (int) $user['id']];
    }

    /**
     * Establish the authenticated session state.
     *
     * @param int $userId Authenticated user identifier.
     * @param array<string, mixed> $user The user row.
     * @return void
     */
    private function startSession(int $userId, array $user): void
    {
        // Rotating the id here is what prevents session fixation: an attacker
        // who planted a known id before login cannot reuse it afterwards.
        $this->session->regenerate();
        $this->csrf->rotate();

        $this->session->set('user_id', $userId);
        $this->session->set('username', (string) $user['username']);
        $this->session->set('role', (string) ($user['role'] ?? 'user'));
        $this->session->set('display_name', (string) ($user['display_name'] ?? $user['username']));

        $this->users->touchLastLogin($userId);
    }

    /**
     * End the current session.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->session->destroy();
    }

    /**
     * @return int The authenticated user identifier, or zero when signed out.
     */
    public function userId(): int
    {
        return (int) $this->session->get('user_id', 0);
    }

    /**
     * @return bool True when a user is authenticated.
     */
    public function check(): bool
    {
        return $this->userId() > 0;
    }

    /**
     * @return string The authenticated user's role, or an empty string.
     */
    public function role(): string
    {
        return (string) $this->session->get('role', '');
    }

    /**
     * @return bool True when the current user may moderate content.
     */
    public function isModerator(): bool
    {
        return in_array($this->role(), ['moderator', 'admin'], true);
    }

    /**
     * @return bool True when the current user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role() === 'admin';
    }

    /**
     * Build the throttle key for one client and username combination.
     *
     * @param string $clientKey Client identifier, usually the remote address.
     * @param string $username Attempted login name.
     * @return string A fixed-length key that does not store the raw address.
     */
    private function attemptKey(string $clientKey, string $username): string
    {
        return hash('sha256', mb_strtolower($username, 'UTF-8') . '|' . $clientKey);
    }

    /**
     * @param string $attemptKey Throttle key.
     * @return bool True when the key is currently locked out.
     */
    private function isLockedOut(string $attemptKey): bool
    {
        $row = $this->database->selectOne(
            'SELECT locked_until FROM login_attempts WHERE attempt_key = :key',
            ['key' => $attemptKey],
        );

        if ($row === null || $row['locked_until'] === null) {
            return false;
        }

        return strtotime((string) $row['locked_until']) > time();
    }

    /**
     * Record one failed attempt, locking the key once the limit is reached.
     *
     * @param string $attemptKey Throttle key.
     * @return void
     */
    private function recordFailure(string $attemptKey): void
    {
        $existing = $this->database->selectOne(
            'SELECT failed_count FROM login_attempts WHERE attempt_key = :key',
            ['key' => $attemptKey],
        );

        if ($existing === null) {
            $this->database->execute(
                'INSERT INTO login_attempts (attempt_key, failed_count, first_failed_at)
                 VALUES (:key, 1, CURRENT_TIMESTAMP)',
                ['key' => $attemptKey],
            );
        } else {
            $this->database->execute(
                'UPDATE login_attempts SET failed_count = failed_count + 1 WHERE attempt_key = :key',
                ['key' => $attemptKey],
            );
        }

        $count = (int) ($existing['failed_count'] ?? 0) + 1;

        if ($count >= self::MAX_FAILED_ATTEMPTS) {
            $this->database->execute(
                'UPDATE login_attempts
                 SET locked_until = :until
                 WHERE attempt_key = :key',
                [
                    'until' => date('Y-m-d H:i:s', time() + self::LOCKOUT_SECONDS),
                    'key' => $attemptKey,
                ],
            );
        }
    }

    /**
     * Clear the throttle after a successful login.
     *
     * @param string $attemptKey Throttle key.
     * @return void
     */
    private function clearFailures(string $attemptKey): void
    {
        $this->database->execute(
            'DELETE FROM login_attempts WHERE attempt_key = :key',
            ['key' => $attemptKey],
        );
    }
}
