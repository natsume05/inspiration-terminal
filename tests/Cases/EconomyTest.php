<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Database\Database;
use App\Repository\EconomyRepository;
use App\Repository\RateLimitRepository;
use App\Repository\UserRepository;
use App\Service\EconomyService;
use App\Support\Config;
use Tests\Bootstrap;
use Tests\TestCase;

/**
 * Business-rule tests for the stardust economy.
 *
 * These cover the exact defects found in the previous implementation:
 * repeated check-in granting unlimited currency, comment rewards with no daily
 * cap, double purchases, and spending more stardust than the user holds.
 */
final class EconomyTest extends TestCase
{
    private Database $database;

    private EconomyService $economy;

    private UserRepository $users;

    protected function setUp(): void
    {
        $this->database = Bootstrap::seeded();
        $this->users = new UserRepository($this->database);

        $this->economy = new EconomyService(
            $this->database,
            $this->users,
            new EconomyRepository($this->database),
            new RateLimitRepository($this->database),
            new Config(require dirname(__DIR__, 2) . '/config/app.php'),
        );
    }

    /**
     * The daily check-in must pay out at most once per day.
     */
    public function testCheckInIsIdempotentWithinTheSameDay(): void
    {
        $startingBalance = $this->users->balance(1);

        $first = $this->economy->checkIn(1);
        $this->assertTrue($first['ok'], 'first check-in succeeds');
        $this->assertTrue($this->users->balance(1) > $startingBalance, 'first check-in increases the balance');

        $balanceAfterFirst = $this->users->balance(1);

        // The previous implementation allowed this to pay out repeatedly.
        $second = $this->economy->checkIn(1);
        $this->assertFalse($second['ok'], 'second check-in in the same day is refused');
        $this->assertSame($balanceAfterFirst, $this->users->balance(1), 'refused check-in leaves the balance untouched');
    }

    /**
     * Repeated attempts must not keep incrementing the daily counter, or the
     * counter would drift upward and eventually block an unrelated action.
     */
    public function testRepeatedCheckInDoesNotInflateTheDailyCounter(): void
    {
        $this->economy->checkIn(1);
        $this->economy->checkIn(1);
        $this->economy->checkIn(1);

        $counter = (new RateLimitRepository($this->database))->count(1, 'checkin', date('Y-m-d'));

        $this->assertSame(1, $counter, 'daily counter stays at one after three attempts');
    }

    /**
     * The reward amount must stay inside the configured band.
     */
    public function testCheckInRewardStaysWithinConfiguredRange(): void
    {
        $before = $this->users->balance(1);
        $result = $this->economy->checkIn(1);
        $gained = $this->users->balance(1) - $before;

        $this->assertTrue($result['ok'], 'check-in succeeded');
        $this->assertBetween(20, 50, $gained, 'reward falls within the configured 20-50 range');
    }

    /**
     * Comment rewards must stop after the daily cap. This is the fix for the
     * unlimited-currency loop in the previous version.
     */
    public function testCommentRewardStopsAtTheDailyCap(): void
    {
        $before = $this->users->balance(1);
        $awarded = 0;

        // Attempt well past the configured cap of five.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $result = $this->economy->rewardComment(1);

            if ($result['awarded']) {
                $awarded++;
            }
        }

        $this->assertSame(5, $awarded, 'exactly five comments were rewarded');
        $this->assertSame($before + 10, $this->users->balance(1), 'stardust gained equals five rewards of two');
    }

    /**
     * A purchase must move stardust and ownership together.
     */
    public function testPurchaseDebitsAndGrantsOwnership(): void
    {
        $before = $this->users->balance(1);
        $result = $this->economy->purchase(1, 1);

        $this->assertTrue($result['ok'], 'purchase of an affordable item succeeds');
        $this->assertSame($before - 50, $this->users->balance(1), 'balance is reduced by the price');
        $this->assertTrue(
            (new EconomyRepository($this->database))->ownsItem(1, 1),
            'the purchased item is now owned',
        );
    }

    /**
     * Buying the same item twice must not charge twice.
     */
    public function testDoublePurchaseIsRejectedAndDoesNotChargeTwice(): void
    {
        $this->economy->purchase(1, 1);
        $afterFirst = $this->users->balance(1);

        $second = $this->economy->purchase(1, 1);

        $this->assertFalse($second['ok'], 'second purchase of the same item is refused');
        $this->assertSame($afterFirst, $this->users->balance(1), 'the refused purchase did not charge the account');
    }

    /**
     * An unaffordable purchase must be refused without changing the balance.
     */
    public function testUnaffordablePurchaseLeavesBalanceIntact(): void
    {
        $before = $this->users->balance(1);
        $result = $this->economy->purchase(1, 2);

        $this->assertFalse($result['ok'], 'purchase beyond the balance is refused');
        $this->assertSame($before, $this->users->balance(1), 'balance is unchanged after a refused purchase');
        $this->assertFalse(
            (new EconomyRepository($this->database))->ownsItem(1, 2),
            'the unaffordable item was not granted',
        );
    }

    /**
     * A nonexistent or withdrawn item must not be purchasable.
     */
    public function testPurchaseOfUnknownItemIsRejected(): void
    {
        $result = $this->economy->purchase(1, 9999);

        $this->assertFalse($result['ok'], 'unknown item is refused');
        $this->assertSame(100, $this->users->balance(1), 'balance is untouched');
    }

    /**
     * The daily draw must be limited to one attempt per day.
     */
    public function testDailyDrawIsLimitedToOnePerDay(): void
    {
        $first = $this->economy->draw(1);
        $this->assertTrue($first['ok'], 'first draw succeeds');

        $second = $this->economy->draw(1);
        $this->assertFalse($second['ok'], 'second draw on the same day is refused');
    }

    /**
     * The draw must spend nothing: it is a reward, not a purchase.
     */
    public function testDrawNeverReducesTheBalance(): void
    {
        $before = $this->users->balance(1);
        $this->economy->draw(1);

        $this->assertTrue($this->users->balance(1) >= $before, 'balance never decreases from a draw');
    }

    /**
     * Draft items must never leave a user worse off, which is what a negative
     * balance would mean.
     */
    public function testBalanceNeverBecomesNegativeThroughSpending(): void
    {
        // Price 500 against a balance of 100: must fail cleanly.
        $this->economy->purchase(1, 2);

        $this->assertTrue($this->users->balance(1) >= 0, 'balance remains non-negative');
    }

    /**
     * The void drop must respect its daily cap of one.
     */
    public function testVoidDropPaysAtMostOncePerDay(): void
    {
        $drops = 0;

        // The drop is probabilistic, so drive it many times to be confident the
        // cap holds once it triggers.
        for ($attempt = 0; $attempt < 400; $attempt++) {
            if ($this->economy->rollVoidDrop(1)['dropped']) {
                $drops++;
            }
        }

        $this->assertTrue($drops <= 1, 'void drop triggered at most once across 400 attempts');
    }

    /**
     * Equipping a second item of the same type must unequip the first, so the
     * user never wears two effects at once.
     */
    public function testEquippingSameTypeReplacesThePreviousItem(): void
    {
        $economy = new EconomyRepository($this->database);

        // Grant both effects directly: items 1 and 2 are both of type `effect`.
        $economy->grantItem(2, 1);
        $economy->grantItem(2, 2);

        $economy->equip(2, 1);
        $equippedAfterFirst = $this->equippedCount($economy, 2);
        $this->assertSame(1, $equippedAfterFirst, 'one effect equipped after the first equip');

        $economy->equip(2, 2);
        $this->assertSame(1, $this->equippedCount($economy, 2), 'still only one effect equipped after switching');

        $rows = $this->database->select(
            'SELECT item_id FROM user_items WHERE user_id = 2 AND is_equipped = 1',
        );
        $this->assertSame(2, (int) $rows[0]['item_id'], 'the newly equipped item is the active one');
    }

    /**
     * Equipping an item the user does not own must be refused.
     */
    public function testEquippingAnUnownedItemIsRejected(): void
    {
        $economy = new EconomyRepository($this->database);

        $this->assertFalse($economy->equip(2, 1), 'equipping an unowned item returns false');
    }

    /**
     * Count the equipped items for a user.
     *
     * @param EconomyRepository $economy Repository under test.
     * @param int $userId User identifier.
     * @return int Number of equipped rows.
     */
    private function equippedCount(EconomyRepository $economy, int $userId): int
    {
        return count($economy->equippedDecorations($userId));
    }
}
