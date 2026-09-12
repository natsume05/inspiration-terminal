<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Data access for the generic daily throttle table.
 *
 * One table covers every rate-limited action, so adding a new throttled feature
 * does not require a schema change. Rows are created on first use inside the
 * same statement that increments them.
 */
final class RateLimitRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Atomically increment an action counter for today and report the new value.
     *
     * The increment is expressed as a single upsert so two concurrent requests
     * cannot both read the same starting value and then write the same result.
     *
     * @param int $userId User identifier.
     * @param string $action Action key, e.g. `checkin`.
     * @param string $date Day the counter applies to, as `YYYY-MM-DD`.
     * @return int The counter value after incrementing.
     */
    public function increment(int $userId, string $action, string $date): int
    {
        if ($this->database->driver() === 'sqlite') {
            $this->database->execute(
                'INSERT INTO rate_limits (user_id, action, window_date, counter)
                 VALUES (:user_id, :action, :window_date, 1)
                 ON CONFLICT (user_id, action, window_date)
                 DO UPDATE SET counter = counter + 1',
                ['user_id' => $userId, 'action' => $action, 'window_date' => $date],
            );
        } else {
            $this->database->execute(
                'INSERT INTO rate_limits (user_id, action, window_date, counter)
                 VALUES (:user_id, :action, :window_date, 1)
                 ON DUPLICATE KEY UPDATE counter = counter + 1',
                ['user_id' => $userId, 'action' => $action, 'window_date' => $date],
            );
        }

        return $this->count($userId, $action, $date);
    }

    /**
     * Read the current counter without changing it.
     *
     * @param int $userId User identifier.
     * @param string $action Action key.
     * @param string $date Day the counter applies to.
     * @return int The stored counter, or zero when no row exists.
     */
    public function count(int $userId, string $action, string $date): int
    {
        $row = $this->database->selectOne(
            'SELECT counter FROM rate_limits
             WHERE user_id = :user_id AND action = :action AND window_date = :window_date',
            ['user_id' => $userId, 'action' => $action, 'window_date' => $date],
        );

        return (int) ($row['counter'] ?? 0);
    }

    /**
     * Reserve one unit of an action, failing when the daily cap is reached.
     *
     * @param int $userId User identifier.
     * @param string $action Action key.
     * @param string $date Day the counter applies to.
     * @param int $cap Maximum permitted uses per day.
     * @return bool True when a unit was reserved, false when the cap is reached.
     */
    public function attempt(int $userId, string $action, string $date, int $cap): bool
    {
        if ($cap <= 0) {
            return false;
        }

        // Reserve first, then check: a single statement decides the winner, so
        // simultaneous requests cannot both pass a "read then write" check.
        $used = $this->increment($userId, $action, $date);

        if ($used > $cap) {
            // Over the cap, so hand the reservation back and refuse.
            $this->database->execute(
                'UPDATE rate_limits SET counter = counter - 1
                 WHERE user_id = :user_id AND action = :action AND window_date = :window_date',
                ['user_id' => $userId, 'action' => $action, 'window_date' => $date],
            );

            return false;
        }

        return true;
    }

    /**
     * Remove counters older than the given date to keep the table small.
     *
     * @param string $beforeDate Cutoff date as `YYYY-MM-DD`.
     * @return int Number of rows removed.
     */
    public function pruneBefore(string $beforeDate): int
    {
        return $this->database->execute(
            'DELETE FROM rate_limits WHERE window_date < :before',
            ['before' => $beforeDate],
        );
    }
}
