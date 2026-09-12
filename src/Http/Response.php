<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Builds the outgoing response.
 *
 * Controllers return a Response so that emitting headers and body stays in one
 * place, which is what allows the security headers to be attached to every
 * response without each page remembering to do it.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    private int $status = 200;

    private string $body = '';

    /**
     * @param string $body Response body.
     * @param int $status HTTP status code.
     * @param array<string, string> $headers Additional headers.
     */
    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    /**
     * Create an HTML response.
     *
     * @param string $html Markup.
     * @param int $status HTTP status code.
     * @return self
     */
    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Create a JSON response.
     *
     * @param array<string, mixed> $payload Data to serialise.
     * @param int $status HTTP status code.
     * @return self
     */
    public static function json(array $payload, int $status = 200): self
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self(
            $encoded === false ? '{"error":"encoding failed"}' : $encoded,
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /**
     * Create a redirect response.
     *
     * Only same-origin targets are allowed, so a crafted parameter cannot turn
     * the site into an open redirector.
     *
     * @param string $location Target path.
     * @param int $status HTTP status code.
     * @return self
     */
    public static function redirect(string $location, int $status = 302): self
    {
        if (!str_starts_with($location, '/') || str_starts_with($location, '//')) {
            $location = '/';
        }

        return new self('', $status, ['Location' => $location]);
    }

    /**
     * Create a plain-text response, used for API errors.
     *
     * @param string $text Message body.
     * @param int $status HTTP status code.
     * @return self
     */
    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Add or replace a header.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @return int HTTP status code.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return string Response body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string> Response headers.
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return bool True when the body is an HTML document.
     */
    public function isHtml(): bool
    {
        return str_starts_with($this->headers['Content-Type'] ?? '', 'text/html');
    }

    /**
     * Write the status, headers, and body to the client.
     *
     * @return void
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo $this->body;
    }
}
