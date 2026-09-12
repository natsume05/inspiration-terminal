<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;

/**
 * Data access for the stardust currency and the item shop.
 *
 * Balance changes are always expressed as relative SQL updates
 * (`stardust = stardust - :price`) rather than read-modify-write in PHP, so
 * two concurrent purchases cannot both spend the same balance.
 */
final class EconomyRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Add or subtract stardust and experience.
     *
     * @param int $userId User identifier.
     * @param int $stardustDelta Amount to add; may be negative.
     * @param int $expDelta Experience to add; may be negative.
     * @return void
     */
    public function adjust(int $userId, int $stardustDelta, int $expDelta = 0): void
    {
        $this->database->execute(
            'UPDATE user_profiles
             SET stardust = stardust + :stardust, exp = exp + :exp, updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id',
            ['stardust' => $stardustDelta, 'exp' => $expDelta, 'user_id' => $userId],
        );
    }

    /**
     * Spend stardust only when the balance is sufficient.
     *
     * The guard lives in the WHERE clause, so the check and the deduction are a
     * single atomic operation.
     *
     * @param int $userId User identifier.
     * @param int $amount Amount to spend, must be positive.
     * @return bool True when the balance was debited.
     */
    public function debit(int $userId, int $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        $affected = $this->database->execute(
            'UPDATE user_profiles SET stardust = stardust - :amount, updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND stardust >= :amount',
            ['amount' => $amount, 'user_id' => $userId],
        );

        return $affected > 0;
    }

    /**
     * Grant an item, ignoring the request when it is already owned.
     *
     * @param int $userId User identifier.
     * @param int $itemId Item identifier.
     * @return bool True when the item was newly granted.
     */
    public function grantItem(int $userId, int $itemId): bool
    {
        $affected = $this->database->execute(
            $this->database->driver() === 'sqlite'
                ? 'INSERT INTO user_items (user_id, item_id) VALUES (:user_id, :item_id)
                   ON CONFLICT (user_id, item_id) DO NOTHING'
                : 'INSERT IGNORE INTO user_items (user_id, item_id) VALUES (:user_id, :item_id)',
            ['user_id' => $userId, 'item_id' => $itemId],
        );

        return $affected > 0;
    }

    /**
     * @param int $userId User identifier.
     * @param int $itemId Item identifier.
     * @return bool True when the user owns the item.
     */
    public function ownsItem(int $userId, int $itemId): bool
    {
        return $this->database->selectOne(
            'SELECT 1 AS owned FROM user_items WHERE user_id = :user_id AND item_id = :item_id',
            ['user_id' => $userId, 'item_id' => $itemId],
        ) !== null;
    }

    /**
     * Find a purchasable shop item.
     *
     * @param int $itemId Item identifier.
     * @return array<string, mixed>|null The item row, or null when unavailable.
     */
    public function findForSale(int $itemId): ?array
    {
        return $this->database->selectOne(
            'SELECT id, code, name, description, type, rarity, price, icon, css_class
             FROM shop_items WHERE id = :id AND is_forsale = 1',
            ['id' => $itemId],
        );
    }

    /**
     * List every item on sale.
     *
     * @return list<array<string, mixed>> Item rows.
     */
    public function itemsForSale(): array
    {
        return $this->database->select(
            'SELECT id, code, name, description, type, rarity, price, icon, css_class
             FROM shop_items WHERE is_forsale = 1 ORDER BY price ASC, id ASC',
        );
    }

    /**
     * List item identifiers the user already owns.
     *
     * @param int $userId User identifier.
     * @return list<int> Owned item identifiers.
     */
    public function ownedItemIds(int $userId): array
    {
        $rows = $this->database->select(
            'SELECT item_id FROM user_items WHERE user_id = :user_id',
            ['user_id' => $userId],
        );

        return array_map(static fn (array $row): int => (int) $row['item_id'], $rows);
    }

    /**
     * Pick a random unowned item of a given rarity.
     *
     * @param int $userId User identifier.
     * @param string $rarity Rarity to draw from.
     * @return array<string, mixed>|null The chosen item, or null when none remain.
     */
    public function randomUnownedItem(int $userId, string $rarity): ?array
    {
        // Selecting one candidate at random in PHP avoids `ORDER BY RAND()`,
        // which sorts the entire table and cannot use an index.
        $statement = $this->database->pdo()->prepare(
            'SELECT s.id, s.code, s.name, s.icon, s.rarity
             FROM shop_items s
             WHERE s.rarity = :rarity
               AND s.is_forsale = 1
               AND NOT EXISTS (
                   SELECT 1 FROM user_items ui WHERE ui.item_id = s.id AND ui.user_id = :user_id
               )',
        );
        $statement->execute(['rarity' => $rarity, 'user_id' => $userId]);

        /** @var list<array<string, mixed>> $candidates */
        $candidates = $statement->fetchAll();

        if ($candidates === []) {
            return null;
        }

        return $candidates[random_int(0, count($candidates) - 1)];
    }

    /**
     * Equip an item, unequipping others of the same type first.
     *
     * The two updates share a transaction so a reader never observes the user
     * wearing two items of the same kind.
     *
     * @param int $userId User identifier.
     * @param int $itemId Item identifier.
     * @return bool True when the item is now equipped.
     */
    public function equip(int $userId, int $itemId): bool
    {
        return $this->database->transaction(function (Database $database) use ($userId, $itemId): bool {
            $item = $database->selectOne(
                'SELECT s.type FROM user_items ui
                 JOIN shop_items s ON s.id = ui.item_id
                 WHERE ui.user_id = :user_id AND ui.item_id = :item_id',
                ['user_id' => $userId, 'item_id' => $itemId],
            );

            if ($item === null) {
                return false;
            }

            $database->execute(
                'UPDATE user_items SET is_equipped = 0
                 WHERE user_id = :user_id
                   AND item_id IN (SELECT s.id FROM shop_items s WHERE s.type = :type)',
                ['user_id' => $userId, 'type' => $item['type']],
            );

            $database->execute(
                'UPDATE user_items SET is_equipped = 1 WHERE user_id = :user_id AND item_id = :item_id',
                ['user_id' => $userId, 'item_id' => $itemId],
            );

            return true;
        });
    }

    /**
     * Remove every equipped item of a kind.
     *
     * @param int $userId User identifier.
     * @param int $itemId Item whose type should be unequipped.
     * @return bool True when an item was unequipped.
     */
    public function unequip(int $userId, int $itemId): bool
    {
        return $this->database->transaction(function (Database $database) use ($userId, $itemId): bool {
            $affected = $database->execute(
                'UPDATE user_items SET is_equipped = 0
                 WHERE user_id = :user_id AND item_id = :item_id AND is_equipped = 1',
                ['user_id' => $userId, 'item_id' => $itemId],
            );

            return $affected > 0;
        });
    }

    /**
     * Read the decorations the user currently has equipped.
     *
     * @param int $userId User identifier.
     * @return list<array<string, mixed>> Equipped items with their CSS classes.
     */
    public function equippedDecorations(int $userId): array
    {
        return $this->database->select(
            'SELECT s.type, s.name, s.icon, s.css_class
             FROM user_items ui
             JOIN shop_items s ON s.id = ui.item_id
             WHERE ui.user_id = :user_id AND ui.is_equipped = 1',
            ['user_id' => $userId],
        );
    }
}
