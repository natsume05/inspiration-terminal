<?php

/**
 * Application configuration.
 *
 * Every value is read from the environment with a safe local-development
 * fallback. Secrets must never be committed: copy `.env.example` to `.env`
 * and set real values there, or inject them through the web server.
 */

declare(strict_types=1);

return [
    'app' => [
        'name' => '灵感传输终端',
        'env' => getenv('APP_ENV') ?: 'local',
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
        'url' => getenv('APP_URL') ?: 'http://localhost/inspiration-terminal/public',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',
        'locale' => getenv('APP_LOCALE') ?: 'zh_CN',
    ],

    'database' => [
        'driver' => getenv('DB_DRIVER') ?: 'mysql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_DATABASE') ?: 'my_forum',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        // SQLite is used only by the automated test suite.
        'sqlite_path' => getenv('DB_SQLITE_PATH') ?: '',
    ],

    'session' => [
        'name' => getenv('SESSION_NAME') ?: 'inspiration_session',
        // Idle timeout in seconds. Sessions older than this are discarded.
        'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 7200),
        'cookie_secure' => filter_var(getenv('SESSION_COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL),
        'cookie_samesite' => getenv('SESSION_COOKIE_SAMESITE') ?: 'Lax',
        // Absolute path for session files. Left empty, the application uses
        // `storage/sessions` so it never depends on the host's shared temp
        // directory, which is frequently unwritable on shared hosting and fails
        // silently when it is.
        'save_path' => getenv('SESSION_SAVE_PATH') ?: '',
    ],

    'security' => [
        // Derives data-encryption keys for private notes. Rotating this value
        // makes every existing encrypted note unreadable.
        'app_key' => getenv('APP_KEY') ?: '',
        // Global request throttle: max requests per IP per minute on write endpoints.
        'rate_limit_per_minute' => (int) (getenv('RATE_LIMIT_PER_MINUTE') ?: 60),
        // Content Security Policy. Set to false to disable the header entirely.
        'csp' => getenv('CSP_ENABLED') === 'false' ? false : implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "media-src 'self'",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]),
    ],

    'uploads' => [
        'directory' => getenv('UPLOAD_DIR') ?: dirname(__DIR__) . '/storage/uploads',
        'public_prefix' => '/uploads',
        'max_bytes' => (int) (getenv('UPLOAD_MAX_BYTES') ?: 5 * 1024 * 1024),
        // Uploaded images are re-encoded; only these formats are accepted.
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'max_pixels' => (int) (getenv('UPLOAD_MAX_PIXELS') ?: 40_000_000),
    ],

    'integrations' => [
        'github' => [
            'token' => getenv('GITHUB_TOKEN') ?: '',
            'user_agent' => getenv('GITHUB_USER_AGENT') ?: 'InspirationTerminal/2.0',
            'cache_ttl' => (int) (getenv('GITHUB_CACHE_TTL') ?: 3600),
        ],
        'steam' => [
            // CheapShark is a public read-only API used for Steam discount data.
            'endpoint' => getenv('STEAM_ENDPOINT') ?: 'https://www.cheapshark.com/api/1.0',
            'cache_ttl' => (int) (getenv('STEAM_CACHE_TTL') ?: 900),
        ],
    ],

    'economy' => [
        // Stardust granted per rewarded action. Rewards are capped per day so the
        // currency cannot be farmed by repeating cheap actions such as commenting.
        'rewards' => [
            'checkin' => ['min' => 20, 'max' => 50, 'exp' => 20],
            'comment' => ['amount' => 2, 'daily_cap' => 5],
            'post' => ['exp' => 10, 'daily_cap' => 5],
            'void_drop' => ['chance_percent' => 5, 'min' => 5, 'max' => 20, 'daily_cap' => 1],
            'gacha' => ['daily_cap' => 1],
        ],
    ],
];
