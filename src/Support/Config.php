<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Immutable configuration accessor supporting dot-notation lookups.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items;

    /**
     * @param array<string, mixed> $items Nested configuration array.
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Load configuration from a PHP file returning an array.
     *
     * @param string $path Absolute path to the configuration file.
     * @return self
     */
    public static function fromFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new RuntimeException(sprintf('Configuration file not found: %s', $path));
        }

        $items = require $path;

        if (!is_array($items)) {
            throw new RuntimeException(sprintf('Configuration file must return an array: %s', $path));
        }

        return new self($items);
    }

    /**
     * Read a configuration value using dot notation, e.g. `database.host`.
     *
     * @param string $key Dot-notation key.
     * @param mixed $default Value returned when the key is absent.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Read a configuration value and fail loudly when it is missing.
     *
     * @param string $key Dot-notation key.
     * @return mixed
     */
    public function require(string $key): mixed
    {
        $value = $this->get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Missing required configuration value: %s', $key));
        }

        return $value;
    }

    /**
     * @return bool True when the application runs in the local environment.
     */
    public function isLocal(): bool
    {
        return $this->get('app.env') === 'local';
    }

    /**
     * @return bool True when debug output is enabled.
     */
    public function isDebug(): bool
    {
        return (bool) $this->get('app.debug', false);
    }
}
