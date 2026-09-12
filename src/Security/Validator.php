<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

/**
 * Validates and normalises user input.
 *
 * The old code read request values with `(int) $_POST['x']` and `trim()`, which
 * accepted empty strings, unbounded lengths, and silently coerced junk to zero.
 * This validator rejects invalid input explicitly and reports which field failed
 * so the UI can show a useful message.
 */
final class Validator
{
    /** @var array<string, string> Field name to first error message. */
    private array $errors = [];

    /** @var array<string, mixed> Sanitised values for fields that passed. */
    private array $validated = [];

    /**
     * @param array<string, mixed> $input Raw input, typically `$_POST`.
     */
    public function __construct(private readonly array $input)
    {
    }

    /**
     * Validate a string field.
     *
     * @param string $field Field name.
     * @param int $minLength Minimum accepted length after trimming.
     * @param int $maxLength Maximum accepted length after trimming.
     * @param bool $required Whether an empty value is an error.
     * @return self
     */
    public function string(string $field, int $minLength = 1, int $maxLength = 255, bool $required = true): self
    {
        $value = $this->input[$field] ?? null;

        if (!is_string($value)) {
            if ($required) {
                $this->errors[$field] = '此字段为必填项。';
            }

            return $this;
        }

        $value = trim($value);

        if ($value === '') {
            if ($required) {
                $this->errors[$field] = '此字段不能为空。';
            }

            return $this;
        }

        $length = mb_strlen($value, 'UTF-8');

        if ($length < $minLength) {
            $this->errors[$field] = sprintf('内容至少需要 %d 个字符。', $minLength);

            return $this;
        }

        if ($length > $maxLength) {
            $this->errors[$field] = sprintf('内容不能超过 %d 个字符。', $maxLength);

            return $this;
        }

        $this->validated[$field] = $value;

        return $this;
    }

    /**
     * Validate a positive integer identifier.
     *
     * @param string $field Field name.
     * @param bool $required Whether the field must be present.
     * @return self
     */
    public function id(string $field, bool $required = true): self
    {
        $value = $this->input[$field] ?? null;

        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$field] = '缺少必要的参数。';
            }

            return $this;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($filtered === false) {
            $this->errors[$field] = '参数格式不正确。';

            return $this;
        }

        $this->validated[$field] = $filtered;

        return $this;
    }

    /**
     * Validate a value against a fixed set of allowed options.
     *
     * @param string $field Field name.
     * @param list<string> $allowed Permitted values.
     * @param string|null $default Value used when the field is absent.
     * @return self
     */
    public function inList(string $field, array $allowed, ?string $default = null): self
    {
        $value = $this->input[$field] ?? null;

        if ($value === null || $value === '') {
            if ($default !== null) {
                $this->validated[$field] = $default;
            } else {
                $this->errors[$field] = '参数取值不合法。';
            }

            return $this;
        }

        if (!is_string($value) || !in_array($value, $allowed, true)) {
            $this->errors[$field] = '参数取值不合法。';

            return $this;
        }

        $this->validated[$field] = $value;

        return $this;
    }

    /**
     * Validate an email address.
     *
     * @param string $field Field name.
     * @param bool $required Whether an empty value is an error.
     * @return self
     */
    public function email(string $field, bool $required = false): self
    {
        $value = $this->input[$field] ?? null;

        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$field] = '请输入邮箱地址。';
            }

            return $this;
        }

        $filtered = filter_var(is_string($value) ? trim($value) : '', FILTER_VALIDATE_EMAIL);

        if ($filtered === false || mb_strlen($filtered) > 190) {
            $this->errors[$field] = '邮箱地址格式不正确。';

            return $this;
        }

        $this->validated[$field] = $filtered;

        return $this;
    }

    /**
     * Validate a username: letters, digits, underscore, hyphen, and CJK.
     *
     * @param string $field Field name.
     * @param int $minLength Minimum length.
     * @param int $maxLength Maximum length.
     * @return self
     */
    public function username(string $field, int $minLength = 2, int $maxLength = 32): self
    {
        $this->string($field, $minLength, $maxLength);

        if (isset($this->errors[$field])) {
            return $this;
        }

        $value = (string) $this->validated[$field];

        if (preg_match('/^[\p{Han}\p{Latin}\p{N}_-]+$/u', $value) !== 1) {
            $this->errors[$field] = '代号只能包含中文、字母、数字、下划线和连字符。';

            return $this;
        }

        return $this;
    }

    /**
     * Enforce a minimum password strength.
     *
     * @param string $field Field name.
     * @param int $minLength Minimum length.
     * @return self
     */
    public function password(string $field, int $minLength = 8): self
    {
        $value = $this->input[$field] ?? null;

        if (!is_string($value) || $value === '') {
            $this->errors[$field] = '请输入密钥。';

            return $this;
        }

        // Length is the dominant factor for resistance to offline cracking; the
        // previous implementation had no length requirement at all.
        if (mb_strlen($value, 'UTF-8') < $minLength) {
            $this->errors[$field] = sprintf('密钥至少需要 %d 个字符。', $minLength);

            return $this;
        }

        if (mb_strlen($value, 'UTF-8') > 200) {
            $this->errors[$field] = '密钥过长。';

            return $this;
        }

        $this->validated[$field] = $value;

        return $this;
    }

    /**
     * Require that two password fields match.
     *
     * @param string $field Field holding the confirmation value.
     * @param string $originalField Field holding the original value.
     * @return self
     */
    public function matches(string $field, string $originalField): self
    {
        $original = $this->validated[$originalField] ?? null;
        $confirmation = $this->input[$field] ?? null;

        if (!is_string($original) || !is_string($confirmation) || !hash_equals($original, $confirmation)) {
            $this->errors[$field] = '两次输入的密钥不一致。';
        }

        return $this;
    }

    /**
     * @return bool True when no validation errors were recorded.
     */
    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return bool True when at least one validation error was recorded.
     */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string, string> Field name to error message.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return string The first error message, or an empty string.
     */
    public function firstError(): string
    {
        return $this->errors === [] ? '' : (string) reset($this->errors);
    }

    /**
     * @return array<string, mixed> The validated values.
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Read one validated value, failing loudly if it was never validated.
     *
     * @param string $field Field name.
     * @return mixed
     */
    public function value(string $field): mixed
    {
        if (!array_key_exists($field, $this->validated)) {
            throw new InvalidArgumentException(sprintf('Field "%s" has no validated value.', $field));
        }

        return $this->validated[$field];
    }
}
