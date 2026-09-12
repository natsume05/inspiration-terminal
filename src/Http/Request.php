<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Read-only view of the incoming request.
 *
 * Reading input through one object makes the unsafe HTTP methods explicit and
 * gives the router a single place to enforce CSRF checks for every route.
 */
final class Request
{
    /**
     * @param string $method HTTP method in upper case.
     * @param string $path Request path with the query string removed.
     * @param array<string, mixed> $query Parsed query string.
     * @param array<string, mixed> $body Parsed request body.
     * @param array<string, mixed> $files Uploaded files.
     * @param array<string, string> $headers Request headers with lower-case names.
     * @param array<string, mixed> $server Server environment.
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $headers,
        private readonly array $server,
    ) {
    }

    /**
     * Build a request from the current PHP superglobals.
     *
     * @return self
     */
    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // Method override lets an HTML form issue DELETE or PUT, since browsers
        // cannot submit those methods directly.
        $override = $_POST['_method'] ?? null;
        if ($method === 'POST' && is_string($override)) {
            $candidate = strtoupper($override);
            if (in_array($candidate, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $candidate;
            }
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        $headers = self::collectHeaders();

        return new self(
            $method,
            self::normalisePath($path),
            $_GET,
            self::parseBody($headers),
            $_FILES,
            $headers,
            $_SERVER,
        );
    }

    /**
     * Read the request body, decoding JSON payloads.
     *
     * `$_POST` is only populated for form content types, so a JSON request body
     * — which is what the front-end modules send — would otherwise be invisible
     * and every field would look missing.
     *
     * @param array<string, string> $headers Lower-case request headers.
     * @return array<string, mixed> Parsed body fields.
     */
    private static function parseBody(array $headers): array
    {
        $contentType = $headers['content-type'] ?? '';

        if (!str_contains(strtolower($contentType), 'application/json')) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        // A malformed body yields no fields; the validator then reports the
        // missing parameter rather than the request being trusted.
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build a request for a test or internal call.
     *
     * @param string $method HTTP method.
     * @param string $path Request path.
     * @param array<string, mixed> $body Request body.
     * @param array<string, mixed> $query Query parameters.
     * @param array<string, string> $headers Request headers, lower-case names.
     * @return self
     */
    public static function create(string $method, string $path, array $body = [], array $query = [], array $headers = []): self
    {
        return new self(
            strtoupper($method),
            self::normalisePath($path),
            $query,
            $body,
            [],
            $headers,
            [],
        );
    }

    /**
     * Strip the trailing slash so `/blog/` and `/blog` match one route.
     *
     * @param string $path Raw path.
     * @return string Normalised path, always starting with a slash.
     */
    private static function normalisePath(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * Collect request headers from the server environment.
     *
     * @return array<string, string> Header names in lower case.
     */
    private static function collectHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        // Content-Type and Content-Length arrive without the HTTP_ prefix.
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
                $headers[$name] = $_SERVER[$key];
            }
        }

        return $headers;
    }

    /**
     * @return string Upper-case HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * @return string Normalised request path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return bool True for methods that may change state.
     */
    public function isUnsafe(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * @return bool True when the client expects a JSON response.
     */
    public function wantsJson(): bool
    {
        return str_contains($this->header('accept') ?? '', 'application/json')
            || $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    /**
     * Read a value from the query string or body, preferring the body.
     *
     * @param string $key Parameter name.
     * @param mixed $default Value returned when absent.
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Read a query-string value.
     *
     * @param string $key Parameter name.
     * @param mixed $default Value returned when absent.
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed> The full request body.
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * Read a header by lower-case name.
     *
     * @param string $name Header name.
     * @return string|null The header value, or null when absent.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return string The client address, or an empty string when unavailable.
     */
    public function ip(): string
    {
        $address = $this->server['REMOTE_ADDR'] ?? '';

        return is_string($address) ? $address : '';
    }

    /**
     * @return string The user agent, truncated to the column width.
     */
    public function userAgent(): string
    {
        $agent = $this->header('user-agent') ?? '';

        return mb_substr($agent, 0, 255);
    }

    /**
     * Read one entry from the uploaded files array.
     *
     * @param string $key Field name.
     * @return array<string, mixed>|null The file entry, or null when absent.
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }
}
