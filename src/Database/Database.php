<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Owns the single PDO connection used by the whole application.
 *
 * Centralising connection handling keeps three guarantees in one place:
 * real (non-emulated) prepared statements, exceptions instead of silent
 * failures, and a transaction helper that always rolls back on error.
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    /** @var int Depth of nested transaction calls. */
    private int $transactionDepth = 0;

    /** @var string Project root, used to resolve relative SQLite paths. */
    private string $basePath;

    /**
     * @param array<string, mixed> $config The `database` configuration block.
     */
    public function __construct(private readonly array $config)
    {
        $this->basePath = (string) ($config['base_path'] ?? getcwd() ?: '.');
        $this->pdo = $this->connect();
    }

    /**
     * Create the shared instance from configuration.
     *
     * @param array<string, mixed> $config The `database` configuration block.
     * @return self
     */
    public static function boot(array $config): self
    {
        self::$instance = new self($config);

        return self::$instance;
    }

    /**
     * @return self The shared instance, booting it from configuration if needed.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Database has not been booted yet.');
        }

        return self::$instance;
    }

    /**
     * Build the PDO connection for the configured driver.
     *
     * @return PDO
     */
    private function connect(): PDO
    {
        $driver = (string) ($this->config['driver'] ?? 'mysql');

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements: the driver, not PHP, does the escaping.
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $path = (string) ($this->config['sqlite_path'] ?? ':memory:');
                $pdo = new PDO('sqlite:' . $this->resolveSqlitePath($path), null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->exec('PRAGMA journal_mode = WAL');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    (string) ($this->config['host'] ?? '127.0.0.1'),
                    (int) ($this->config['port'] ?? 3306),
                    (string) ($this->config['name'] ?? ''),
                    (string) ($this->config['charset'] ?? 'utf8mb4'),
                );

                $pdo = new PDO(
                    $dsn,
                    (string) ($this->config['username'] ?? ''),
                    (string) ($this->config['password'] ?? ''),
                    $options,
                );

                // Keep the database session in the same timezone as the app so
                // stored timestamps and "x minutes ago" rendering agree.
                $offset = (new \DateTimeImmutable('now'))->format('P');
                $pdo->exec(sprintf("SET time_zone = '%s'", $offset));

                // Strict mode is mandatory, not cosmetic. Without
                // STRICT_TRANS_TABLES the server silently coerces out-of-range
                // values instead of rejecting them: writing -5 to an UNSIGNED
                // column becomes 0, and an invalid ENUM becomes an empty string.
                // Both would quietly store data that contradicts the schema, and
                // CHECK constraints never fire because the value is corrected
                // before they are evaluated. XAMPP's bundled MariaDB ships
                // without it, so it is set explicitly here rather than assumed.
                $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            }
        } catch (PDOException $exception) {
            // Never surface driver messages to the browser: they leak
            // credentials and schema details. Log, then report generically.
            error_log('[DB] Connection failed: ' . $exception->getMessage());

            throw new RuntimeException('Database connection failed.', 0, $exception);
        }

        return $pdo;
    }

    /**
     * Resolve a SQLite path so it does not depend on the server's working
     * directory.
     *
     * A relative path such as `storage/dev.sqlite` would otherwise resolve
     * against whatever directory the web server happens to run in, which breaks
     * the moment the document root is not the shell's current directory.
     *
     * @param string $path Configured path, or `:memory:`.
     * @return string An absolute path, or `:memory:` unchanged.
     */
    private function resolveSqlitePath(string $path): string
    {
        if ($path === ':memory:' || $path === '') {
            return ':memory:';
        }

        // Recognise both Unix and Windows absolute paths.
        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : $this->basePath . '/' . ltrim($path, '/\\');
    }

    /**
     * @return PDO The underlying connection.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @return string The active driver name, either `mysql` or `sqlite`.
     */
    public function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Run a statement and return every row.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Bound parameters.
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * Run a statement and return the first row, or null when there is none.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Bound parameters.
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Run a write statement.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Bound parameters.
     * @return int Number of affected rows.
     */
    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->rowCount();
    }

    /**
     * Insert a row and return its generated identifier.
     *
     * @param string $sql SQL with named placeholders.
     * @param array<string, mixed> $params Bound parameters.
     * @return int The last inserted identifier.
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Run a callback inside a transaction, rolling back on any throwable.
     *
     * Nested calls reuse the outer transaction so service methods can compose
     * without every layer opening its own transaction.
     *
     * @param callable(self): mixed $callback Work to perform atomically.
     * @return mixed The callback's return value.
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        }

        $this->transactionDepth++;

        try {
            $result = $callback($this);
            $this->transactionDepth--;

            if ($this->transactionDepth === 0) {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $throwable) {
            $this->transactionDepth--;

            if ($this->transactionDepth === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * Execute a raw multi-statement SQL string (used by the schema runner).
     *
     * @param string $sql One or more statements separated by semicolons.
     * @return void
     */
    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }
}
