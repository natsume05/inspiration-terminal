<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;

/**
 * Minimal behaviour-focused test case.
 *
 * A framework is not required to prove the security and economy rules hold, and
 * keeping the suite dependency-free means it runs on any machine with PHP. When
 * PHPUnit is installed the suite can run alongside it; this class covers the
 * assertions these tests actually need.
 */
abstract class TestCase
{
    private int $passed = 0;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> Messages describing what each step verified. */
    private array $notes = [];

    /**
     * Run every method whose name starts with `test`.
     *
     * Each test gets a fresh fixture from {@see self::setUp()}.
     *
     * @return array{passed: int, failures: list<string>}
     */
    public function run(): array
    {
        $methods = array_filter(
            get_class_methods($this),
            static fn (string $method): bool => str_starts_with($method, 'test'),
        );

        foreach ($methods as $method) {
            $this->setUp();

            try {
                $this->{$method}();
            } catch (\Throwable $throwable) {
                $this->failures[] = sprintf(
                    '%s::%s threw %s: "%s" at %s:%d',
                    static::class,
                    $method,
                    $throwable::class,
                    $throwable->getMessage(),
                    basename($throwable->getFile()),
                    $throwable->getLine(),
                );
            }
        }

        return ['passed' => $this->passed, 'failures' => $this->failures];
    }

    /**
     * Prepare a clean fixture before each test method.
     *
     * @return void
     */
    protected function setUp(): void
    {
    }

    /**
     * Assert two values are identical.
     *
     * @param mixed $expected Expected value.
     * @param mixed $actual Actual value.
     * @param string $message Description of the expectation.
     * @return void
     */
    protected function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected === $actual) {
            $this->pass($message);

            return;
        }

        $this->failures[] = sprintf(
            '%s — expected %s, got %s',
            $message,
            $this->describe($expected),
            $this->describe($actual),
        );
    }

    /**
     * Assert a condition is true.
     *
     * @param bool $condition Condition under test.
     * @param string $message Description of the expectation.
     * @return void
     */
    protected function assertTrue(bool $condition, string $message): void
    {
        $this->assertSame(true, $condition, $message);
    }

    /**
     * Assert a condition is false.
     *
     * @param bool $condition Condition under test.
     * @param string $message Description of the expectation.
     * @return void
     */
    protected function assertFalse(bool $condition, string $message): void
    {
        $this->assertSame(false, $condition, $message);
    }

    /**
     * Assert that a callable throws.
     *
     * @param callable(): mixed $callback Code expected to fail.
     * @param class-string<\Throwable> $expectedException Exception type expected.
     * @param string $message Description of the expectation.
     * @return void
     */
    protected function assertThrows(callable $callback, string $expectedException, string $message): void
    {
        try {
            $callback();
        } catch (\Throwable $throwable) {
            if ($throwable instanceof $expectedException) {
                $this->pass($message);

                return;
            }

            $this->failures[] = sprintf(
                '%s — expected %s, got %s ("%s")',
                $message,
                $expectedException,
                $throwable::class,
                $throwable->getMessage(),
            );

            return;
        }

        $this->failures[] = sprintf('%s — nothing was thrown', $message);
    }

    /**
     * Assert that a value falls within an inclusive range.
     *
     * @param int $minimum Lower bound.
     * @param int $maximum Upper bound.
     * @param int $actual Observed value.
     * @param string $message Description of the expectation.
     * @return void
     */
    protected function assertBetween(int $minimum, int $maximum, int $actual, string $message): void
    {
        if ($actual >= $minimum && $actual <= $maximum) {
            $this->pass($message);

            return;
        }

        $this->failures[] = sprintf(
            '%s — expected between %d and %d, got %d',
            $message,
            $minimum,
            $maximum,
            $actual,
        );
    }

    /**
     * Record a note describing an observation that is reported with the results.
     *
     * @param string $note Observation text.
     * @return void
     */
    protected function note(string $note): void
    {
        $this->notes[] = $note;
    }

    /**
     * @return list<string> Notes recorded during this test case.
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * @return list<string> Failure messages.
     */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * @return int Count of passing assertions.
     */
    public function passedCount(): int
    {
        return $this->passed;
    }

    /**
     * Record a passing assertion.
     *
     * @param string $message Description of what passed.
     * @return void
     */
    private function pass(string $message): void
    {
        $this->passed++;
        $this->notes[] = '  ok  ' . $message;
    }

    /**
     * Render a value for a failure message.
     *
     * @param mixed $value Value to describe.
     * @return string Printable representation.
     */
    private function describe(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_scalar($value) => (string) $value,
            default => get_debug_type($value),
        };
    }

    /**
     * Fail the current test with an explicit message.
     *
     * @param string $message Reason for the failure.
     * @return never
     */
    protected function fail(string $message): never
    {
        throw new RuntimeException($message);
    }
}
