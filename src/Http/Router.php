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
    /** @var array<string, array<string, callable>> Method to path to handler. */
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
     * @param string $path Route path.
     * @param callable(Request): Response $handler Route handler.
     * @return void
     */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     *
     * @param string $path Route path.
     * @param callable(Request): Response $handler Route handler.
     * @return void
     */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path Route path.
     * @param callable(Request): Response $handler Route handler.
     * @return void
     */
    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    /**
     * Register a handler for one method and path.
     *
     * @param string $method HTTP method.
     * @param string $path Route path.
     * @param callable(Request): Response $handler Route handler.
     * @return void
     */
    private function add(string $method, string $path, callable $handler): void
    {
        $normalised = rtrim($path, '/');

        if ($normalised === '') {
            $normalised = '/';
        }

        $this->routes[$method][$normalised] = $handler;
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

        $handler = $this->routes[$request->method()][$path] ?? null;

        if ($handler === null) {
            return $this->notFound($request);
        }

        $response = $handler($request);

        if (!$response instanceof Response) {
            throw new RuntimeException(sprintf('Route %s %s did not return a Response.', $request->method(), $path));
        }

        return $response;
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
     * @return array<string, list<string>> Registered paths grouped by method.
     */
    public function registeredRoutes(): array
    {
        $summary = [];

        foreach ($this->routes as $method => $paths) {
            $summary[$method] = array_keys($paths);
        }

        return $summary;
    }
}
