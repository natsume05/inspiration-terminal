<?php

declare(strict_types=1);

namespace App\Http;

use App\Database\Database;
use App\Repository\EconomyRepository;
use App\Repository\PostRepository;
use App\Repository\RateLimitRepository;
use App\Repository\UserRepository;
use App\Security\Csrf;
use App\Security\Crypto;
use App\Security\ImageUploader;
use App\Security\Session;
use App\Service\AuthService;
use App\Service\EconomyService;
use App\Support\Config;
use App\Support\Env;

/**
 * Application entry point: builds the object graph and dispatches one request.
 *
 * Wiring lives here rather than inside individual pages, so a page cannot
 * accidentally create a second database connection, skip session hardening, or
 * forget to register security headers.
 */
final class Kernel
{
    private Config $config;

    private Database $database;

    private Session $session;

    private Csrf $csrf;

    private AuthService $auth;

    /**
     * @param string $basePath Absolute path to the project root.
     */
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Build every shared service.
     *
     * @return self
     */
    public function boot(): self
    {
        // Load .env before configuration so environment overrides are visible.
        Env::load($this->basePath . '/.env');

        $this->config = Config::fromFile($this->basePath . '/config/app.php');

        date_default_timezone_set((string) $this->config->get('app.timezone', 'UTC'));

        // Errors are always logged, but only shown when debugging is enabled, so
        // a production deployment never leaks paths or credentials.
        ini_set('display_errors', $this->config->isDebug() ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        $this->configureSessionStorage();

        /** @var array<string, mixed> $databaseConfig */
        $databaseConfig = $this->config->get('database', []);
        // Relative SQLite paths must resolve against the project root rather
        // than the web server's working directory.
        $databaseConfig['base_path'] = $this->basePath;
        $this->database = Database::boot($databaseConfig);

        $this->session = new Session($this->config);
        $this->csrf = new Csrf($this->session);

        $users = new UserRepository($this->database);
        $this->auth = new AuthService($this->database, $users, $this->session, $this->csrf);

        return $this;
    }

    /**
     * Point PHP's session storage at a directory the application owns.
     *
     * The host's shared temp directory is often unwritable on shared hosting,
     * and when it is, `session_start()` fails with a warning while the rest of
     * the request continues — so logins silently never persist. Writing sessions
     * under `storage/` (outside the document root, git-ignored) removes that
     * dependency and makes the failure mode obvious instead of silent.
     *
     * @return void
     */
    private function configureSessionStorage(): void
    {
        $configured = (string) $this->config->get('session.save_path', '');

        if ($configured === '') {
            $configured = $this->basePath . '/storage/sessions';
        }

        if (!is_dir($configured) && !mkdir($configured, 0700, true) && !is_dir($configured)) {
            throw new \RuntimeException(sprintf('Session directory is not writable: %s', $configured));
        }

        if (!is_writable($configured)) {
            throw new \RuntimeException(sprintf('Session directory is not writable: %s', $configured));
        }

        session_save_path($configured);
    }

    /**
     * @return Config The loaded configuration.
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * @return Database The shared database connection.
     */
    public function database(): Database
    {
        return $this->database;
    }

    /**
     * @return Session The hardened session wrapper.
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * @return Csrf The CSRF token service.
     */
    public function csrf(): Csrf
    {
        return $this->csrf;
    }

    /**
     * @return AuthService The authentication service.
     */
    public function auth(): AuthService
    {
        return $this->auth;
    }

    /**
     * Build the economy service.
     *
     * @return EconomyService
     */
    public function economy(): EconomyService
    {
        return new EconomyService(
            $this->database,
            new UserRepository($this->database),
            new EconomyRepository($this->database),
            new RateLimitRepository($this->database),
            $this->config,
        );
    }

    /**
     * Build the post service.
     *
     * @return PostRepository
     */
    public function posts(): PostRepository
    {
        return new PostRepository($this->database);
    }

    /**
     * Build the uploader from configuration.
     *
     * @return ImageUploader
     */
    public function uploader(): ImageUploader
    {
        return new ImageUploader(
            (string) $this->config->get('uploads.directory'),
            (string) $this->config->get('uploads.public_prefix', '/uploads'),
            (int) $this->config->get('uploads.max_bytes', 5_242_880),
            (int) $this->config->get('uploads.max_pixels', 40_000_000),
        );
    }

    /**
     * Build the note encryption service.
     *
     * @return Crypto
     */
    public function crypto(): Crypto
    {
        $key = (string) $this->config->get('security.app_key', '');

        if ($key === '') {
            // Failing loudly is deliberate: silently storing plaintext after
            // claiming encryption is the defect this class exists to remove.
            throw new \RuntimeException('APP_KEY is not configured, so private notes cannot be encrypted.');
        }

        return new Crypto($key);
    }

    /**
     * Handle one request and send the response.
     *
     * @param callable(Router, self): void $registerRoutes Callback that declares routes.
     * @return void
     */
    public function handle(callable $registerRoutes): void
    {
        // The session must exist before the router can verify a CSRF token.
        $this->session->start();

        $request = Request::fromGlobals();
        $router = new Router($this->csrf);

        $registerRoutes($router, $this);

        try {
            $response = $router->dispatch($request);
        } catch (\Throwable $throwable) {
            error_log(sprintf(
                '[HTTP] Unhandled %s for %s %s: %s',
                $throwable::class,
                $request->method(),
                $request->path(),
                $throwable->getMessage(),
            ));

            $response = $this->config->isDebug()
                ? Response::html(
                    '<pre>' . htmlspecialchars((string) $throwable, ENT_QUOTES, 'UTF-8') . '</pre>',
                    500,
                )
                : Response::html('<h1>服务暂时不可用</h1><p>请稍后再试。</p>', 500);
        }

        $headers = new \App\Security\SecurityHeaders($this->config);
        $headers->apply($response->isHtml());

        $response->send();
    }
}
