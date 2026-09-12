<?php

declare(strict_types=1);

namespace App\Http;

use App\Security\Csrf;
use RuntimeException;

/**
 * Maps request paths to handlers and enforces CSRF protection centrally.
 *
 * The previous codebase verified CSRF tokens in two of seven state-changing
 * endpoints because each file had to remember to do it. Here the check lives in
 * {@see self::dispatch()}, so a route cannot be added without it.
 */
final class Router
{
    /** @var array<string, list<array{pattern: string, handler: callable, names: list<string>}>> Method to routes. */
    private array $routes = [];

    /**
     * @param Csrf $csrf Token verifier applied to unsafe methods.
     */
    public function __construct(private readonly Csrf $csrf)
    {
    }

    /**
     * Register a GET route.
     *
     * @param string $path Route path, optionally containing `{name}` placeholders.
     * @param callable(Request, string...): Response $handler Route handler.
     * @return void
     */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $path Route path, optionally containing `{name}` placeholders.
     * @param callable(Request, string...): Response $handler Route handler.
     * @return void
     */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path Route path, optionally containing `{name}` placeholders.
     * @param callable(Request, string...): Response $handler Route handler.
     * @return void
     */
    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    /**
     * Register a handler for one method and path.
     *
     * A `{name}` segment matches one path component and is passed to the handler
     * as a string argument. Placeholders deliberately do not span slashes, so a
     * slug can never swallow the rest of the path.
     *
     * @param string $method HTTP method.
     * @param string $path Route path.
     * @param callable $handler Route handler.
     * @return void
     */
    private function add(string $method, string $path, callable $handler): void
    {
        $normalised = rtrim($path, '/');

        if ($normalised === '') {
            $normalised = '/';
        }

        $names = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$names): string {
                $names[] = $matches[1];

                return '([^/]+)';
            },
            $normalised,
        );

        $this->routes[$method][] = [
            'pattern' => '#^' . ($regex ?? $normalised) . '$#',
            'handler' => $handler,
            'names' => $names,
        ];
    }

    /**
     * Resolve and execute the handler for a request.
     *
     * @param Request $request The incoming request.
     * @return Response The response to send.
     */
    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        // Every unsafe request must carry a valid token before any handler runs,
        // for HTML forms and JSON endpoints alike.
        if ($request->isUnsafe() && !$this->csrfIsValid($request)) {
            return $this->csrfFailure($request);
        }

        $route = $this->match($request->method(), $path);

        if ($route === null) {
            return $this->notFound($request);
        }

        $response = ($route['handler'])($request, ...$route['parameters']);

        if (!$response instanceof Response) {
            throw new RuntimeException(sprintf('Route %s %s did not return a Response.', $request->method(), $path));
        }

        return $response;
    }

    /**
     * Find the handler registered for a method and path.
     *
     * @param string $method HTTP method.
     * @param string $path Request path.
     * @return array{handler: callable, parameters: list<string>}|null The match, or null.
     */
    private function match(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            array_shift($matches);

            return [
                'handler' => $route['handler'],
                'parameters' => array_map(static fn (mixed $value): string => rawurldecode((string) $value), $matches),
            ];
        }

        return null;
    }

    /**
     * Verify the CSRF token from the body or the header.
     *
     * @param Request $request The incoming request.
     * @return bool True when the token is valid.
     */
    private function csrfIsValid(Request $request): bool
    {
        $submitted = $request->input($this->csrf->fieldName());

        if (!is_string($submitted) || $submitted === '') {
            // JSON clients send the token as a header instead of a form field.
            $submitted = $request->header($this->csrf->headerName());
        }

        return $this->csrf->verify($submitted);
    }

    /**
     * Build the response for a rejected CSRF check.
     *
     * @param Request $request The rejected request.
     * @return Response A 403 response in the shape the client expects.
     */
    private function csrfFailure(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json([
                'ok' => false,
                'error' => 'csrf_token_invalid',
                'message' => '请求校验失败，请刷新页面后重试。',
            ], 403);
        }

        return Response::html(
            '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<title>请求已拒绝</title></head><body>'
            . '<h1>请求已拒绝</h1>'
            . '<p>安全令牌无效或已过期，请返回上一页刷新后重试。</p>'
            . '</body></html>',
            403,
        );
    }

    /**
     * Build the 404 response.
     *
     * @param Request $request The unmatched request.
     * @return Response A 404 response.
     */
    private function notFound(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => 'not_found', 'message' => '接口不存在。'], 404);
        }

        return Response::html(
            '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<title>404 · 信号丢失</title></head><body>'
            . '<h1>404</h1><p>这里没有你寻找的信号。</p>'
            . '<p><a href="/">返回首页</a></p>'
            . '</body></html>',
            404,
        );
    }

    /**
     * @return array<string, list<string>> Registered patterns grouped by method.
     */
    public function registeredRoutes(): array
    {
        $summary = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $summary[$method][] = $route['pattern'];
            }
        }

        return $summary;
    }
}
