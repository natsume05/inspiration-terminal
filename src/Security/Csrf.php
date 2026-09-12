<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Per-session CSRF token generation and verification.
 *
 * The previous codebase shipped this class but only called it from two of the
 * seven state-changing endpoints. Here the token is verified by the router for
 * every unsafe HTTP method, so a new endpoint cannot forget it.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const FIELD_NAME = 'csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';

    /**
     * @param Session $session Session holding the token.
     */
    public function __construct(private readonly Session $session)
    {
    }

    /**
     * Return the current token, creating it on first use.
     *
     * @return string A 64-character hexadecimal token.
     */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * @return string The form field name carrying the token.
     */
    public function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    /**
     * @return string The HTTP header name carrying the token.
     */
    public function headerName(): string
    {
        return self::HEADER_NAME;
    }

    /**
     * Verify a submitted token in constant time.
     *
     * @param mixed $submitted Token value received from the client.
     * @return bool True when the token is present and matches.
     */
    public function verify(mixed $submitted): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '' || !is_string($submitted)) {
            return false;
        }

        // hash_equals keeps the comparison time independent of how many leading
        // characters matched.
        return hash_equals($expected, $submitted);
    }

    /**
     * Rotate the token, for use after login or logout.
     *
     * @return string The new token.
     */
    public function rotate(): string
    {
        $this->session->forget(self::SESSION_KEY);

        return $this->token();
    }
}
