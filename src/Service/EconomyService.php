<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\Database;
use App\Repository\EconomyRepository;
use App\Repository\RateLimitRepository;
use App\Repository\UserRepository;
use App\Support\Config;
use RuntimeException;

/**
 * The stardust economy: daily check-in, rewarded actions, the item shop, and
 * the daily draw.
 *
 * Every reward path funnels through {@see self::awardOnce()} or
 * {@see self::awardCapped()}, which is the structural fix for the previous
 * design where "comment for +2 stardust" had no limit and the currency could be
 * farmed indefinitely by repeating a cheap action.
 *
 * All balance changes are applied as relative SQL updates inside a transaction,
 * so concurrent requests cannot spend the same balance twice.
 */
final class EconomyService
{
    /** @var array<string, mixed> */
    private array $rewards;

    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly EconomyRepository $economy,
        private readonly RateLimitRepository $limits,
        private readonly Config $config,
    ) {
        /** @var array<string, mixed> $rewards */
        $rewards = $this->config->get('economy.rewards', []);
        $this->rewards = $rewards;
    }

    /**
     * Claim the daily check-in reward.
     *
     * The daily limit row doubles as the idempotency guard: the first call
     * inserts it, and every later call in the same day is refused before any
     * balance changes.
     *
     * @param int $userId User identifier.
     * @return array{ok: bool, message: string, stardust?: int, exp?: int, balance?: int}
     */
    public function checkIn(int $userId): array
    {
        /** @var array{min: int, max: int, exp: int} $reward */
        $reward = $this->rewards['checkin'] ?? ['min' => 20, 'max' => 50, 'exp' => 20];

        return $this->database->transaction(function () use ($userId, $reward): array {
            // attempt() reserves a slot atomically; a second request in the same
            // day loses the race and is refused here.
            if (!$this->limits->attempt($userId, 'checkin', $this->today(), 1)) {
                return ['ok' => false, 'message' => '今日补给已领取，明天再来吧！'];
            }

            $stardust = random_int($reward['min'], $reward['max']);
            $this->economy->adjust($userId, $stardust, $reward['exp']);

            return [
                'ok' => true,
                'message' => sprintf('签到成功！获得 %d 星尘、%d 经验。', $stardust, $reward['exp']),
                'stardust' => $stardust,
                'exp' => $reward['exp'],
                'balance' => $this->users->balance($userId),
            ];
        });
    }

    /**
     * Award the comment reward, capped per day.
     *
     * Without the cap a user could post one-character comments in a loop and
     * mint unlimited currency, which is exactly what the previous version
     * allowed.
     *
     * @param int $userId User identifier.
     * @return array{awarded: bool, amount: int, balance: int}
     */
    public function rewardComment(int $userId): array
    {
        /** @var array{amount: int, daily_cap: int} $reward */
        $reward = $this->rewards['comment'] ?? ['amount' => 2, 'daily_cap' => 5];

        return $this->awardCapped($userId, 'comment_reward', $reward['amount'], $reward['daily_cap']);
    }

    /**
     * Award the post reward, capped per day.
     *
     * @param int $userId User identifier.
     * @return array{awarded: bool, amount: int, balance: int}
     */
    public function rewardPost(int $userId): array
    {
        /** @var array{exp: int, daily_cap: int} $reward */
        $reward = $this->rewards['post'] ?? ['exp' => 10, 'daily_cap' => 5];

        $result = $this->awardCapped($userId, 'post_exp', 0, $reward['daily_cap'], $reward['exp']);

        return $result;
    }

    /**
     * Roll for a random stardust drop.
     *
     * The probability is applied with `random_int` rather than `rand`, which is
     * not suitable for anything where the outcome has value.
     *
     * @param int $userId User identifier.
     * @return array{dropped: bool, amount: int, message: string}
     */
    public function rollVoidDrop(int $userId): array
    {
        /** @var array{chance_percent: int, min: int, max: int, daily_cap: int} $drop */
        $drop = $this->rewards['void_drop'] ?? ['chance_percent' => 5, 'min' => 5, 'max' => 20, 'daily_cap' => 1];

        // Consume the daily slot only when the roll succeeds, so an unlucky user
        // is not locked out of a drop for the rest of the day.
        if (random_int(1, 100) > $drop['chance_percent']) {
            return ['dropped' => false, 'amount' => 0, 'message' => ''];
        }

        $result = $this->awardCapped($userId, 'void_drop', random_int($drop['min'], $drop['max']), $drop['daily_cap']);

        if (!$result['awarded']) {
            return ['dropped' => false, 'amount' => 0, 'message' => ''];
        }

        return [
            'dropped' => true,
            'amount' => $result['amount'],
            'message' => '🌌 虚空回响：你在探索中发现了微量星尘。',
        ];
    }

    /**
     * Purchase an item with stardust.
     *
     * @param int $userId User identifier.
     * @param int $itemId Item identifier.
     * @return array{ok: bool, message: string, balance?: int}
     */
    public function purchase(int $userId, int $itemId): array
    {
        return $this->database->transaction(function () use ($userId, $itemId): array {
            $item = $this->economy->findForSale($itemId);

            if ($item === null) {
                return ['ok' => false, 'message' => '商品已下架或不存在。'];
            }

            if ($this->economy->ownsItem($userId, $itemId)) {
                return ['ok' => false, 'message' => '你已经拥有此遗物了。'];
            }

            $price = (int) $item['price'];

            // debit() carries the balance check in its WHERE clause, so the
            // check and the deduction cannot be interleaved by another request.
            if (!$this->economy->debit($userId, $price)) {
                return ['ok' => false, 'message' => '星尘不足，去探索虚空吧。'];
            }

            if (!$this->economy->grantItem($userId, $itemId)) {
                // The item was granted concurrently; roll the charge back.
                throw new RuntimeException('物品已被发放，交易回滚。');
            }

            return [
                'ok' => true,
                'message' => '交易完成，遗物已归档。',
                'balance' => $this->users->balance($userId),
            ];
        });
    }

    /**
     * Perform the once-per-day draw.
     *
     * @param int $userId User identifier.
     * @return array{ok: bool, message: string, reward?: array<string, mixed>, balance?: int}
     */
    public function draw(int $userId): array
    {
        /** @var array{daily_cap: int} $config */
        $config = $this->rewards['gacha'] ?? ['daily_cap' => 1];

        return $this->database->transaction(function () use ($userId, $config): array {
            if (!$this->limits->attempt($userId, 'gacha', $this->today(), $config['daily_cap'])) {
                return ['ok' => false, 'message' => '今日虚空共鸣次数已用尽。'];
            }

            $roll = random_int(1, 100);

            // Mostly stardust, with item rarities occupying the upper tail.
            if ($roll <= 60) {
                $amount = random_int(10, 50);
                $this->economy->adjust($userId, $amount);

                return [
                    'ok' => true,
                    'message' => sprintf('获得 %d 星尘碎片。', $amount),
                    'reward' => ['type' => 'stardust', 'name' => '星尘碎片', 'rarity' => 'common', 'amount' => $amount],
                    'balance' => $this->users->balance($userId),
                ];
            }

            $rarity = match (true) {
                $roll === 100 => 'legendary',
                $roll > 95 => 'epic',
                $roll > 85 => 'rare',
                default => 'common',
            };

            $item = $this->economy->randomUnownedItem($userId, $rarity);

            if ($item !== null && $this->economy->grantItem($userId, (int) $item['id'])) {
                return [
                    'ok' => true,
                    'message' => sprintf('抽中了「%s」！', $item['name']),
                    'reward' => [
                        'type' => 'item',
                        'name' => $item['name'],
                        'icon' => $item['icon'],
                        'rarity' => $item['rarity'],
                    ],
                    'balance' => $this->users->balance($userId),
                ];
            }

            // Nothing left to win at this rarity, so pay out stardust instead.
            $amount = $roll > 95 ? 500 : 100;
            $this->economy->adjust($userId, $amount);

            return [
                'ok' => true,
                'message' => sprintf('获得 %d 高纯度星尘结晶。', $amount),
                'reward' => ['type' => 'stardust', 'name' => '高纯度星尘结晶', 'rarity' => 'epic', 'amount' => $amount],
                'balance' => $this->users->balance($userId),
            ];
        });
    }

    /**
     * Reserve a daily slot and pay a reward when the cap allows it.
     *
     * @param int $userId User identifier.
     * @param string $action Action key used for the daily counter.
     * @param int $stardust Stardust to grant.
     * @param int $dailyCap Maximum grants per day.
     * @param int $exp Experience to grant.
     * @return array{awarded: bool, amount: int, balance: int}
     */
    private function awardCapped(int $userId, string $action, int $stardust, int $dailyCap, int $exp = 0): array
    {
        return $this->database->transaction(function () use ($userId, $action, $stardust, $dailyCap, $exp): array {
            if (!$this->limits->attempt($userId, $action, $this->today(), $dailyCap)) {
                return ['awarded' => false, 'amount' => 0, 'balance' => $this->users->balance($userId)];
            }

            if ($stardust !== 0 || $exp !== 0) {
                $this->economy->adjust($userId, $stardust, $exp);
            }

            return [
                'awarded' => $stardust > 0 || $exp > 0,
                'amount' => $stardust,
                'balance' => $this->users->balance($userId),
            ];
        });
    }

    /**
     * @return string Today's date in the application timezone.
     */
    private function today(): string
    {
        return date('Y-m-d');
    }
}
