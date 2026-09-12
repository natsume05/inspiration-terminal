<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Config;
use RuntimeException;

/**
 * Wraps PHP's session handling with hardened defaults.
 *
 * The previous implementation called `session_start()` directly, which left the
 * cookie without `HttpOnly` or `SameSite` protection and made the session id
 * survive indefinitely. This class sets the cookie flags before the session
 * starts and enforces an idle timeout, including periodic id regeneration so a
 * stolen session id has a bounded useful life.
 */
final class Session
{
    private bool $started = false;

    /**
     * @param Config $config Application configuration.
     */
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Start the session with hardened cookie parameters.
     *
     * @return void
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf('Cannot start session: headers already sent at %s:%d', $file, $line));
        }

        $secure = (bool) $this->config->get('session.cookie_secure', false);
        $sameSite = (string) $this->config->get('session.cookie_samesite', 'Lax');

        session_name((string) $this->config->get('session.name', 'inspiration_session'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            // Only send the cookie over HTTPS when the deployment uses it.
            'secure' => $secure,
            // Blocks JavaScript access, so an XSS bug cannot read the session id.
            'httponly' => true,
            // Blocks the cookie from being attached to cross-site requests,
            // which is the second line of defence behind the CSRF token.
            'samesite' => $sameSite,
        ]);

        // Do not accept a session id supplied in the URL.
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        session_start();
        $this->started = true;

        $this->enforceIdleTimeout();
    }

    /**
     * Discard the session when it has been idle for longer than the limit.
     *
     * @return void
     */
    private function enforceIdleTimeout(): void
    {
        $lifetime = (int) $this->config->get('session.lifetime', 7200);
        $lastSeen = (int) ($_SESSION['_last_seen'] ?? 0);
        $now = time();

        if ($lastSeen > 0 && ($now - $lastSeen) > $lifetime) {
            $this->destroy();
            session_start();
        }

        $_SESSION['_last_seen'] = $now;

        // Rotate the session id periodically so a leaked id expires quickly.
        $issuedAt = (int) ($_SESSION['_issued_at'] ?? 0);
        if ($issuedAt === 0) {
            $_SESSION['_issued_at'] = $now;
        } elseif (($now - $issuedAt) > 900) {
            session_regenerate_id(true);
            $_SESSION['_issued_at'] = $now;
        }
    }

    /**
     * Rotate the session id, for use after a privilege change such as login.
     *
     * @return void
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_issued_at'] = time();
        }
    }

    /**
     * Read a session value.
     *
     * @param string $key Session key.
     * @param mixed $default Value returned when the key is absent.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Write a session value.
     *
     * @param string $key Session key.
     * @param mixed $value Value to store.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session value.
     *
     * @param string $key Session key.
     * @return void
     */
    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Flash a one-time message that is removed when it is next read.
     *
     * @param string $type Message category, e.g. `success` or `error`.
     * @param string $message Message body.
     * @return void
     */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * @return array<string, string> All flashed messages, clearing them.
     */
    public function takeFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    /**
     * Destroy the session and expire its cookie.
     *
     * @return void
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: 'PHPSESSID', '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => (string) ($params['samesite'] ?? 'Lax'),
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->started = false;
    }
}
