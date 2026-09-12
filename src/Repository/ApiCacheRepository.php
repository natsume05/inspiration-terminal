<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Cache for third-party API responses.
 *
 * Caching here is what makes the toolbox dependable: the GitHub search endpoint
 * allows only sixty unauthenticated requests per hour, and a page that calls it
 * on every view would exhaust that and then fail. Serving a slightly stale
 * payload is always better than showing an error, so a cache miss after an
 * upstream failure falls back to whatever was stored last.
 */
final class ApiCacheRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Read a cached payload when it has not expired.
     *
     * @param string $key Cache key.
     * @return array<string, mixed>|null The decoded payload, or null when absent or stale.
     */
    public function get(string $key): ?array
    {
        // The comparison bound is computed in PHP rather than with the database's
        // CURRENT_TIMESTAMP. SQLite reports CURRENT_TIMESTAMP in UTC while PHP
        // works in the application timezone, so mixing the two clocks would make
        // an entry look expired for as many hours as the timezone offset.
        $row = $this->database->selectOne(
            'SELECT payload FROM api_cache WHERE cache_key = :key AND expires_at > :now',
            ['key' => $this->normaliseKey($key), 'now' => date('Y-m-d H:i:s')],
        );

        if ($row === null) {
            return null;
        }

        $decoded = json_decode((string) $row['payload'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Read a cached payload regardless of age.
     *
     * Used as a fallback when the upstream call fails, so a page keeps working
     * with the last known good data instead of erroring.
     *
     * @param string $key Cache key.
     * @return array<string, mixed>|null The decoded payload, or null when never stored.
     */
    public function getStale(string $key): ?array
    {
        $row = $this->database->selectOne(
            'SELECT payload FROM api_cache WHERE cache_key = :key',
            ['key' => $this->normaliseKey($key)],
        );

        if ($row === null) {
            return null;
        }

        $decoded = json_decode((string) $row['payload'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Store a payload with a time to live.
     *
     * @param string $key Cache key.
     * @param array<string, mixed> $payload Data to store.
     * @param int $ttlSeconds Lifetime in seconds.
     * @return void
     */
    public function put(string $key, array $payload, int $ttlSeconds): void
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return;
        }

        // The duplicate-key syntax differs between engines, so it is chosen by
        // driver rather than assuming MySQL.
        $conflictClause = $this->database->driver() === 'sqlite'
            ? 'ON CONFLICT (cache_key) DO UPDATE SET payload = excluded.payload, expires_at = excluded.expires_at'
            : 'ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)';

        $this->database->execute(
            'INSERT INTO api_cache (cache_key, payload, expires_at)
             VALUES (:key, :payload, :expires_at)
             ' . $conflictClause,
            [
                'key' => $this->normaliseKey($key),
                'payload' => $encoded,
                'expires_at' => date('Y-m-d H:i:s', time() + max(1, $ttlSeconds)),
            ],
        );
    }

    /**
     * Remove expired entries.
     *
     * @return int Number of rows removed.
     */
    public function pruneExpired(): int
    {
        return $this->database->execute(
            'DELETE FROM api_cache WHERE expires_at < :now',
            ['now' => date('Y-m-d H:i:s')],
        );
    }

    /**
     * Constrain a key to the column width.
     *
     * @param string $key Raw key.
     * @return string A usable key.
     */
    private function normaliseKey(string $key): string
    {
        return mb_substr($key, 0, 190);
    }
}
