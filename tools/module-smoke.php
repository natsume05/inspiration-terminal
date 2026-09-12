<?php

/**
 * Check that every module works against a real database file.
 *
 * The target database is taken from `DB_SQLITE_PATH` rather than defaulting to
 * `storage/dev.sqlite`. This script creates a `smoke_owner` account and a sample
 * blog entry, so pointing it at the development database leaves those rows behind
 * and they turn up in screenshots and demos. `tests/` covers the same modules in
 * memory; this script exists for the cases that need a file-backed database.
 *
 *   DB_DRIVER=sqlite DB_SQLITE_PATH=storage/smoke.sqlite \
 *     APP_KEY=<32-byte-key> php tools/module-smoke.php
 *
 * Setting `DB_DRIVER=mysql` runs the same checks against MySQL or MariaDB. The
 * two engines disagree about things that matter here — a repeated named
 * placeholder is one, `ON DUPLICATE KEY UPDATE` against `ON CONFLICT` is another
 * — and this script exists to catch exactly that, so being able to point it at
 * the engine production uses is the point. It writes fixtures, so it refuses to
 * start unless `SMOKE_ALLOW_MYSQL=1` is set as well, and the target should be a
 * throwaway database rather than the live one.
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

use App\Database\Database;
use App\Database\Migrator;
use App\Repository\ApiCacheRepository;
use App\Repository\AuditRepository;
use App\Repository\BlogRepository;
use App\Repository\EconomyRepository;
use App\Repository\NoteRepository;
use App\Repository\NotificationRepository;
use App\Repository\RateLimitRepository;
use App\Repository\ToolRepository;
use App\Repository\UserRepository;
use App\Security\Crypto;
use App\Security\Csrf;
use App\Security\Session;
use App\Service\AdminService;
use App\Service\AuthService;
use App\Service\EconomyService;
use App\Service\GithubService;
use App\Service\NotificationService;
use App\Service\SteamService;
use App\Support\Config;

$databasePath = getenv('DB_SQLITE_PATH');
$driver = getenv('DB_DRIVER') === 'mysql' ? 'mysql' : 'sqlite';

if ($driver === 'mysql' && getenv('SMOKE_ALLOW_MYSQL') !== '1') {
    fwrite(STDERR, 'DB_DRIVER=mysql writes fixture rows into the database it is given.' . PHP_EOL);
    fwrite(STDERR, 'Point it at a throwaway database and set SMOKE_ALLOW_MYSQL=1 to confirm.' . PHP_EOL);
    fwrite(STDERR, '  DB_DRIVER=mysql DB_DATABASE=inspiration_smoke SMOKE_ALLOW_MYSQL=1 php tools/module-smoke.php' . PHP_EOL);

    exit(2);
}

if ($driver === 'sqlite' && ($databasePath === false || $databasePath === '')) {
    fwrite(STDERR, 'Set DB_SQLITE_PATH to a throwaway database file, for example:' . PHP_EOL);
    fwrite(STDERR, '  DB_SQLITE_PATH=storage/smoke.sqlite php tools/module-smoke.php' . PHP_EOL);
    fwrite(STDERR, PHP_EOL . 'Refusing to fall back to the development database, so smoke fixtures' . PHP_EOL);
    fwrite(STDERR, 'cannot end up in the data used for screenshots and demos.' . PHP_EOL);

    exit(2);
}

$database = $driver === 'mysql'
    ? Database::boot([
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => (string) getenv('DB_DATABASE'),
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ])
    : Database::boot([
        'driver' => 'sqlite',
        'sqlite_path' => $databasePath,
    ]);

echo 'Engine: ' . $database->driver() . PHP_EOL;

// The schema is applied here rather than assumed. Migrations are tracked, so on a
// database that is already up to date this does nothing, and on a fresh one it
// removes a setup step that used to be a separate throwaway script.
$applied = (new Migrator($database, $basePath . '/database/migrations', translateToSqlite: $driver === 'sqlite'))->migrate();

if ($applied !== []) {
    echo 'Applied migrations: ' . implode(', ', $applied) . PHP_EOL;
}

$config = Config::fromFile($basePath . '/config/app.php');
$crypto = new Crypto('0123456789abcdef0123456789abcdef');

$failures = 0;

/**
 * Assert a condition and report it.
 *
 * @param string $label Check description.
 * @param bool $passed Whether the check passed.
 * @param string $detail Extra context.
 * @return void
 */
function check(string $label, bool $passed, string $detail = ''): void
{
    global $failures;

    printf("  [%s] %s%s%s", $passed ? 'PASS' : 'FAIL', $label, $detail !== '' ? ' — ' . $detail : '', PHP_EOL);

    if (!$passed) {
        $failures++;
    }
}

$users = new UserRepository($database);
echo 'Users' . PHP_EOL;

// Fixtures are created here rather than borrowed from a seeding script. The
// check then depends only on the code under test, and a change to the seed data
// cannot make it fail for an unrelated reason. Names are ASCII so a source-file
// encoding difference cannot affect the lookups.
$ownerId = 0;
$existingOwner = $database->selectOne('SELECT id FROM users WHERE username = :u', ['u' => 'smoke_owner']);

if ($existingOwner !== null) {
    $ownerId = (int) $existingOwner['id'];
} else {
    $ownerId = $users->create('smoke_owner', password_hash('smoke-password', PASSWORD_DEFAULT), 'Smoke Owner');
    $database->execute(
        "UPDATE users SET role = 'admin' WHERE id = :id",
        ['id' => $ownerId],
    );
}

check('the owner fixture exists', $ownerId > 0, 'id=' . $ownerId);
check('the owner fixture is an administrator', $users->find($ownerId)['role'] === 'admin');

$user = $users->find($ownerId);
check('the owner account is loaded', $user !== null);
check('avatar_path is exposed for the profile page', $user !== null && array_key_exists('avatar_path', $user));
check('active account list is non-empty', count($users->activeUserIds()) > 0);

// A second real account is needed wherever the schema requires a valid actor:
// notifications and ownership checks both reference users by foreign key, so a
// fabricated id is rejected by the database rather than silently accepted.
$existingActor = $database->selectOne('SELECT id FROM users WHERE username = :u', ['u' => 'smoke_actor']);
$actorId = $existingActor !== null
    ? (int) $existingActor['id']
    : $users->create('smoke_actor', password_hash('smoke-password', PASSWORD_DEFAULT), 'Smoke Actor');

check('a second account exists', $actorId > 0, 'id=' . $actorId);
check('the two accounts are distinct', $actorId !== $ownerId);

echo PHP_EOL . 'Blog' . PHP_EOL;
$blog = new BlogRepository($database);
$id = $blog->create($ownerId, '测试日志标题', "# 小标题\n\n正文 **加粗** 内容。\n\n- 一\n- 二");
$entry = $blog->findPublished((string) $id);
check('entry is created and found by id', $entry !== null);
check('slug is generated', $entry !== null && ($entry['slug'] ?? '') !== '');
check('excerpt strips markup', $entry !== null && !str_contains((string) $entry['excerpt'], '<'));

// A long entry is where the excerpt length and the column width meet. The column
// is VARCHAR(320) on MySQL, which rejects one character over; SQLite accepts
// anything, so only a check like this one catches the difference.
$longBody = str_repeat("## 小节标题\n\n这是一段用来把摘要撑到上限之上的正文内容。\n\n", 40);
$longId = $blog->create($ownerId, '一篇足够长的日志', $longBody);
$longEntry = $database->selectOne('SELECT excerpt FROM blog_posts WHERE id = :blog_id', ['blog_id' => $longId]);
$excerptLength = mb_strlen((string) ($longEntry['excerpt'] ?? ''));

check(
    'a long entry stores an excerpt within the column width',
    $excerptLength > 0 && $excerptLength <= 320,
    'length=' . $excerptLength,
);
check(
    'the long excerpt carries no Markdown markers',
    $longEntry !== null && !str_contains((string) $longEntry['excerpt'], '#') && !str_contains((string) $longEntry['excerpt'], "\n"),
);

$slug = (string) ($entry['slug'] ?? '');
$bySlug = $blog->findPublished($slug);
check('entry is found by slug as well', $bySlug !== null, 'slug=' . $slug);
check('author_id is selected for notifications', $bySlug !== null && isset($bySlug['author_id']));

$blog->addComment($id, $ownerId, 'MingMo', '第一条回响');
$blog->addComment($id, null, '路人', '访客也能评论');
$comments = $blog->comments($id);
check('both member and guest comments are stored', count($comments) === 2, 'count=' . count($comments));

$blog->like($id, $ownerId);
$blog->like($id, $ownerId);
check('duplicate blog like is suppressed', $blog->likeCount($id) === 1, 'count=' . $blog->likeCount($id));
check('like state is reported', $blog->hasLiked($id, $ownerId));

$blog->unlike($id, $ownerId);
check('blog unlike removes the like', $blog->likeCount($id) === 0);
check('published count is at least one', $blog->publishedCount() >= 1);

echo PHP_EOL . 'Notes (encrypted)' . PHP_EOL;
$notes = new NoteRepository($database, $crypto);
$noteId = $notes->create($ownerId, '这是一条私密笔记，包含敏感词 secret-token。');
$stored = $database->selectOne('SELECT ciphertext, nonce FROM private_notes WHERE id = :id', ['id' => $noteId]);
check('ciphertext does not contain the plaintext', $stored !== null && !str_contains((string) $stored['ciphertext'], 'secret-token'));
check('nonce is 12 bytes', $stored !== null && strlen((string) $stored['nonce']) === 12, 'len=' . strlen((string) ($stored['nonce'] ?? '')));
$listed = $notes->forUser($ownerId);
check('note decrypts back to the original text', $listed !== [] && str_contains($listed[0]['content'], 'secret-token'));
check('note is marked readable', $listed !== [] && $listed[0]['readable'] === true);
check('note count is reported', $notes->countForUser($ownerId) >= 1);

// Deleting must be scoped to the owner, so another account cannot remove it.
check('another user cannot delete the note', $notes->delete($noteId, $actorId) === false);
$notes->delete($noteId, $ownerId);
check('the owner can delete the note', $notes->countForUser($ownerId) === 0);

echo PHP_EOL . 'Notifications' . PHP_EOL;
$notifications = new NotificationRepository($database);
$service = new NotificationService($notifications, $users);
check('a comment notification is created', $service->commented($ownerId, $actorId, 'blog', $id) === true);
check('self-notification is refused', $service->commented($ownerId, $ownerId, 'blog', $id) === false);
check('an unknown notification type is refused', $notifications->create($ownerId, 'nonsense', $actorId, 'x') === false);
check('unread count reflects the insert', $service->unreadCount($ownerId) >= 1, 'unread=' . $service->unreadCount($ownerId));

$targeted = $notifications->forUser($ownerId, 10);
check('notification carries the actor name', $targeted !== [] && ($targeted[0]['actor_name'] ?? '') === 'Smoke Actor');

$service->markAllRead($ownerId);
check('marking all read clears the count', $service->unreadCount($ownerId) === 0);

echo PHP_EOL . 'Toolbox' . PHP_EOL;
$tools = new ToolRepository($database);
$groups = $tools->linksByCategory();
check('links are grouped by category', is_array($groups));

// The projects table persists between runs, so the rows this check depends on
// are removed first. Without that, a second run sees the accumulation from the
// first and fails for a reason that has nothing to do with the code.
$database->execute('DELETE FROM github_projects WHERE remote_id = :id', ['id' => 12345]);

$tools->upsertProject([
    'id' => 12345,
    'full_name' => 'example/repo',
    'description' => 'A test repository',
    'html_url' => 'https://github.com/example/repo',
    'stargazers_count' => 4242,
    'forks_count' => 12,
    'language' => 'PHP',
], 'all_time');
$tools->upsertProject([
    'id' => 12345,
    'full_name' => 'example/repo',
    'description' => 'A test repository',
    'html_url' => 'https://github.com/example/repo',
    'stargazers_count' => 5000,
    'forks_count' => 13,
    'language' => 'PHP',
], 'all_time');

$stored = $database->select('SELECT id, stars FROM github_projects WHERE remote_id = :id', ['id' => 12345]);
check('project upsert does not duplicate rows', count($stored) === 1, 'rows=' . count($stored));
check('project stars were refreshed', $stored !== [] && (int) $stored[0]['stars'] === 5000);
check('project search finds the row', count($tools->searchProjects('example', 20)) >= 1);

$github = new GithubService($tools, new ApiCacheRepository($database), $config);
check('github search tolerates a short term', $github->search('e') === []);
check('github search finds the cached row', count($github->search('example')) >= 1);

// Cache rows are cleared first for the same reason as above: this check has to
// produce the same result on every run.
$database->execute('DELETE FROM api_cache WHERE cache_key = :key', ['key' => 'test.key']);
$database->execute('DELETE FROM api_cache WHERE cache_key = :key', ['key' => 'test.stale']);

$cache = new ApiCacheRepository($database);
$cache->put('test.key', ['value' => 1], 60);
check('cache returns a fresh value', ($cache->get('test.key')['value'] ?? null) === 1);

// `put` clamps a nonsensical TTL to one second rather than writing an already
// expired row, so the stale path is exercised by writing a past expiry directly.
$database->execute(
    'INSERT INTO api_cache (cache_key, payload, expires_at) VALUES (:key, :payload, :expires_at)',
    [
        'key' => 'test.stale',
        'payload' => json_encode(['value' => 2]),
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ],
);

check('expired cache is not returned as fresh', $cache->get('test.stale') === null);
check('expired cache is still available as a fallback', ($cache->getStale('test.stale')['value'] ?? null) === 2);

// Pruning is checked against its own row, so this assertion cannot depend on the
// ones above and cannot invalidate them.
$database->execute('DELETE FROM api_cache WHERE cache_key = :key', ['key' => 'test.prune']);
$database->execute(
    'INSERT INTO api_cache (cache_key, payload, expires_at) VALUES (:key, :payload, :expires_at)',
    [
        'key' => 'test.prune',
        'payload' => json_encode(['value' => 3]),
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ],
);

check('pruning removes the expired entry', $cache->pruneExpired() >= 1);
check('the pruned entry is gone', $cache->getStale('test.prune') === null);

// The economy writes to two tables under a balance guard, and it was the one
// module this script did not touch — which is how a statement MySQL refuses
// (`:amount` bound twice) survived in `debit()` while every SQLite run passed.
echo PHP_EOL . 'Economy' . PHP_EOL;

$economy = new EconomyRepository($database);

// Cleared first, for the same reason as the cache rows above: the daily
// check-in writes a per-day limit row, so without this the second run would find
// the day already claimed and report a failure that says nothing about the code.
// Removing the account takes its balance, its items and its limit rows with it.
$database->execute('DELETE FROM users WHERE username = :username', ['username' => 'smoke_spender']);
$database->execute('DELETE FROM shop_items WHERE code = :code', ['code' => 'smoke_item']);
$database->execute('DELETE FROM shop_items WHERE code = :code', ['code' => 'smoke_expensive']);

$spenderId = $database->insert(
    "INSERT INTO users (username, password_hash, role, status) VALUES (:username, :hash, 'user', 'active')",
    ['username' => 'smoke_spender', 'hash' => password_hash('smoke-password', PASSWORD_DEFAULT)],
);
$database->execute(
    'INSERT INTO user_profiles (user_id, display_name, exp, stardust) VALUES (:user_id, :name, 0, 100)',
    ['user_id' => $spenderId, 'name' => 'smoke_spender'],
);

$balance = static fn (): int => (int) $database->selectOne(
    'SELECT stardust FROM user_profiles WHERE user_id = :user_id',
    ['user_id' => $spenderId],
)['stardust'];

$economy->adjust($spenderId, 50, 10);
check('stardust and experience are credited', $balance() === 150, 'balance=' . $balance());

check('a debit within the balance succeeds', $economy->debit($spenderId, 30) === true);
check('the debit is deducted exactly once', $balance() === 120, 'balance=' . $balance());
check('a debit beyond the balance is refused', $economy->debit($spenderId, 10000) === false);
check('the refused debit left the balance alone', $balance() === 120, 'balance=' . $balance());
check('a zero debit is refused', $economy->debit($spenderId, 0) === false);

$itemId = $database->insert(
    "INSERT INTO shop_items (code, name, description, type, rarity, price, icon, css_class)
     VALUES ('smoke_item', '冒烟测试遗物', '只用于自检', 'effect', 'common', 40, '🧪', 'effect-smoke')",
);

$economyService = new EconomyService(
    $database,
    new UserRepository($database),
    $economy,
    new RateLimitRepository($database),
    $config,
);

$purchase = $economyService->purchase($spenderId, $itemId);
check('a purchase within the balance succeeds', ($purchase['ok'] ?? false) === true, (string) ($purchase['message'] ?? ''));
check('the purchase charged the price', $balance() === 80, 'balance=' . $balance());
check('the purchased item is owned', $economy->ownsItem($spenderId, $itemId) === true);
check('buying the same item twice is refused', ($economyService->purchase($spenderId, $itemId)['ok'] ?? true) === false);

$expensiveId = $database->insert(
    "INSERT INTO shop_items (code, name, description, type, rarity, price, icon, css_class)
     VALUES ('smoke_expensive', '昂贵的冒烟遗物', '只用于自检', 'effect', 'legendary', 100000, '💸', 'effect-smoke')",
);
check('a purchase beyond the balance is refused', ($economyService->purchase($spenderId, $expensiveId)['ok'] ?? true) === false);
check('the refused purchase left the balance alone', $balance() === 80, 'balance=' . $balance());

check('the first check-in of the day succeeds', ($economyService->checkIn($spenderId)['ok'] ?? false) === true);
check('a second check-in the same day is refused', ($economyService->checkIn($spenderId)['ok'] ?? true) === false);

$steam = new SteamService($cache, $config);
check('seasonal calendar is available offline', count($steam->calendar()) > 0);

echo PHP_EOL . 'Administration' . PHP_EOL;
$admin = new AdminService(
    $database,
    $blog,
    $tools,
    $users,
    new AuditRepository($database),
    $service,
);

$toolId = $admin->addTool($ownerId, '测试工具', 'https://example.com/tool', '测试描述', 'dev', '🔧');
check('tool is added', $toolId > 0);
check('tool is deleted', $admin->deleteTool($ownerId, $toolId) === true);

check('announcement is published', $admin->publishAnnouncement($ownerId, '这是一条测试广播') === 0);
check('active announcement is readable', ($admin->activeAnnouncement()['content'] ?? '') === '这是一条测试广播');
check('the previous announcement is retired', $admin->clearAnnouncement($ownerId) === true);
check('no active announcement remains', $admin->activeAnnouncement() === null);

check('title is granted', $admin->grantTitle($ownerId, $actorId, '测试称号') === true);
check('unknown account cannot be granted a title', $admin->grantTitle($ownerId, 99999, 'x') === false);
check('role can be changed', $admin->setRole($ownerId, $actorId, 'moderator') === true);
check('an administrator cannot change their own role', $admin->setRole($ownerId, $ownerId, 'user') === false);
check('invalid role is refused', $admin->setRole($ownerId, $actorId, 'root') === false);
check('account can be suspended', $admin->setSuspended($ownerId, $actorId, true) === true);
check('account can be reinstated', $admin->setSuspended($ownerId, $actorId, false) === true);

$counts = $admin->dashboardCounts();
check('dashboard counts are produced', isset($counts['users'], $counts['posts'], $counts['blog']), json_encode($counts, JSON_UNESCAPED_UNICODE));
check('feedback list is readable', is_array($admin->feedback(10)));
check('user list is readable', is_array($admin->users(10)));

$publishedId = $admin->publishBlog($ownerId, '控制台发布的日志', "# 来自控制台\n\n正文内容。");
check('an entry can be published through the admin service', $publishedId > 0, 'id=' . $publishedId);
check('the published entry is readable', $blog->findPublished((string) $publishedId) !== null);
check('the entry can be deleted', $admin->deleteBlog($ownerId, $publishedId) === true);
check('the deleted entry is gone', $blog->findPublished((string) $publishedId) === null);

echo PHP_EOL . 'Audit log' . PHP_EOL;
$audit = new AuditRepository($database);
$audit->record(AuditRepository::LOGIN_FAILED, null, '', null, '203.0.113.5', 'probe');
check('audit entries are recorded', count($audit->recent(10)) > 0);
check('failed logins are counted for an address', $audit->recentFailedLogins('203.0.113.5') >= 1);

// The audit table is only worth having if the paths that matter write to it.
// Recording a row here by hand, as above, proves the table works; it does not
// prove that a refused sign-in reaches it, which is what the throttle's own
// counter is read from.
echo PHP_EOL . 'Sign-in is written to the audit log' . PHP_EOL;

$session = new Session($config);
$auth = new AuthService($database, new UserRepository($database), $session, new Csrf($session), $audit);

$before = count($audit->recent(500));
// Counted before the attempt as well, so a second run of this script compares
// against its own starting point instead of an absolute one.
$failuresBefore = $audit->recentFailedLogins('198.51.100.7');
$auth->attempt('smoke_owner', 'definitely-not-the-password', '198.51.100.7', 'probe');
$after = count($audit->recent(500));

check('a refused sign-in adds an audit entry', $after === $before + 1, sprintf('%d → %d', $before, $after));
check(
    'the refused sign-in is attributed to the client address',
    $audit->recentFailedLogins('198.51.100.7') === $failuresBefore + 1,
    sprintf('%d → %d', $failuresBefore, $audit->recentFailedLogins('198.51.100.7')),
);

$auditedActions = array_column($audit->recent(500), 'action');

check(
    'publishing through the admin service is audited',
    in_array(AuditRepository::BLOG_CREATED, $auditedActions, true)
        && in_array(AuditRepository::BLOG_DELETED, $auditedActions, true),
);

echo PHP_EOL;
printf('%d check(s) failed.%s', $failures, PHP_EOL);

exit($failures === 0 ? 0 : 1);
