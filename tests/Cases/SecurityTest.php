<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Http\Request;
use App\Repository\PostRepository;
use App\Security\Crypto;
use App\Security\ImageUploader;
use App\Security\Validator;
use RuntimeException;
use Tests\Bootstrap;
use Tests\TestCase;

/**
 * Security tests for the layer that the previous implementation only partially
 * applied: upload validation, input validation, at-rest encryption, and the
 * database-level guarantees behind concurrent like requests.
 */
final class SecurityTest extends TestCase
{
    /**
     * Arbitrary-length input must be rejected rather than silently truncated.
     */
    public function testValidatorRejectsOverlongInput(): void
    {
        $validator = new Validator(['content' => str_repeat('字', 2000)]);
        $validator->string('content', 1, 500);

        $this->assertTrue($validator->fails(), 'a 2000-character comment is rejected');
    }

    /**
     * An empty required field must be rejected.
     */
    public function testValidatorRejectsEmptyRequiredField(): void
    {
        $validator = new Validator(['content' => '   ']);
        $validator->string('content');

        $this->assertTrue($validator->fails(), 'whitespace-only content is rejected');
    }

    /**
     * A valid value must pass and be trimmed.
     */
    public function testValidatorAcceptsAndTrimsValidInput(): void
    {
        $validator = new Validator(['content' => '  hello world  ']);
        $validator->string('content', 1, 500);

        $this->assertTrue($validator->passes(), 'valid content passes');
        $this->assertSame('hello world', $validator->value('content'), 'surrounding whitespace is trimmed');
    }

    /**
     * Identifiers must be positive integers, not silently coerced to zero.
     */
    public function testValidatorRejectsNonNumericIdentifier(): void
    {
        $validator = new Validator(['post_id' => 'abc']);
        $validator->id('post_id');

        $this->assertTrue($validator->fails(), 'a non-numeric identifier is rejected');

        $negative = new Validator(['post_id' => '-5']);
        $negative->id('post_id');
        $this->assertTrue($negative->fails(), 'a negative identifier is rejected');
    }

    /**
     * Values outside an allow-list must be rejected, which is what keeps an
     * unexpected category from reaching the database.
     */
    public function testValidatorRejectsValueOutsideAllowList(): void
    {
        $validator = new Validator(['category' => 'not-a-category']);
        $validator->inList('category', ['daily', 'tech']);

        $this->assertTrue($validator->fails(), 'an unknown category is rejected');
    }

    /**
     * Short passwords must be refused, closing the "no length rule" gap.
     */
    public function testValidatorEnforcesMinimumPasswordLength(): void
    {
        $validator = new Validator(['password' => 'short']);
        $validator->password('password');

        $this->assertTrue($validator->fails(), 'a five-character password is rejected');
    }

    /**
     * An email address that is not an address must be rejected.
     */
    public function testValidatorRejectsMalformedEmail(): void
    {
        $validator = new Validator(['email' => 'not-an-email']);
        $validator->email('email');

        $this->assertTrue($validator->fails(), 'a malformed email is rejected');
    }

    /**
     * Usernames must reject characters that would complicate rendering.
     */
    public function testValidatorRejectsUnsafeUsernameCharacters(): void
    {
        $validator = new Validator(['username' => '<script>alert(1)</script>']);
        $validator->username('username');

        $this->assertTrue($validator->fails(), 'a username containing HTML is rejected');
    }

    /**
     * Encryption must round-trip and must not store the plaintext.
     */
    public function testEncryptionRoundTripsAndHidesThePlaintext(): void
    {
        $crypto = new Crypto('test-app-key-that-is-long-enough');
        $secret = '这是一条私密笔记 with a secret token';

        $sealed = $crypto->encrypt($secret);

        $this->assertFalse(
            str_contains($sealed['ciphertext'], '私密笔记'),
            'ciphertext does not contain the plaintext',
        );
        $this->assertSame($secret, $crypto->decrypt($sealed['ciphertext'], $sealed['nonce']), 'decryption restores the note');
    }

    /**
     * The same plaintext must produce different ciphertext each time, which is
     * what a fresh nonce per message guarantees.
     */
    public function testEncryptionUsesAFreshNoncePerMessage(): void
    {
        $crypto = new Crypto('test-app-key-that-is-long-enough');

        $first = $crypto->encrypt('same text');
        $second = $crypto->encrypt('same text');

        $this->assertFalse($first['nonce'] === $second['nonce'], 'each message uses a distinct nonce');
        $this->assertFalse($first['ciphertext'] === $second['ciphertext'], 'identical plaintext yields different ciphertext');
    }

    /**
     * Tampered ciphertext must fail authentication rather than decrypt to
     * garbage, because AES-GCM authenticates the payload.
     */
    public function testTamperedCiphertextIsRejected(): void
    {
        $crypto = new Crypto('test-app-key-that-is-long-enough');
        $sealed = $crypto->encrypt('important note');

        $tampered = $sealed['ciphertext'];
        $tampered[0] = chr(ord($tampered[0]) ^ 0xFF);

        $this->assertThrows(
            static fn (): string => $crypto->decrypt($tampered, $sealed['nonce']),
            RuntimeException::class,
            'modified ciphertext fails authentication',
        );
    }

    /**
     * A different key must not be able to read the note.
     */
    public function testDecryptionWithTheWrongKeyFails(): void
    {
        $sealed = (new Crypto('the-original-application-key'))->encrypt('secret');
        $other = new Crypto('a-completely-different-key!');

        $this->assertThrows(
            static fn (): string => $other->decrypt($sealed['ciphertext'], $sealed['nonce']),
            RuntimeException::class,
            'a different key cannot decrypt the note',
        );
    }

    /**
     * A weak application key must be refused rather than silently used.
     */
    public function testShortApplicationKeyIsRejected(): void
    {
        $this->assertThrows(
            static fn (): Crypto => new Crypto('tooshort'),
            RuntimeException::class,
            'an application key under 16 characters is refused',
        );
    }

    /**
     * A text file renamed as an image must be rejected. The check reads the file
     * contents, so the extension cannot be used to smuggle a payload.
     */
    public function testUploaderRejectsNonImageContent(): void
    {
        $uploader = new ImageUploader(sys_get_temp_dir());
        $scriptPath = $this->temporaryFile('<?php echo "pwned"; ?>');

        $this->assertThrows(
            fn (): string => $uploader->store([
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $scriptPath,
                'size' => strlen('<?php echo "pwned"; ?>'),
                'name' => 'avatar.png',
            ], 'avatars'),
            RuntimeException::class,
            'a PHP script renamed to .png is rejected',
        );
    }

    /**
     * An upload larger than the configured limit must be refused before any
     * decoding happens, since decoding is what consumes the memory.
     */
    public function testUploaderRejectsOversizedFileBeforeDecoding(): void
    {
        $uploader = new ImageUploader(sys_get_temp_dir(), '/uploads', 1024);
        $bigPath = $this->temporaryFile(str_repeat('x', 4096));

        $this->assertThrows(
            fn (): string => $uploader->store([
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $bigPath,
                'size' => 4096,
                'name' => 'big.png',
            ], 'avatars'),
            RuntimeException::class,
            'a file over the byte limit is rejected',
        );
    }

    /**
     * A failed upload must report the transport error rather than proceeding.
     */
    public function testUploaderSurfacesTransportErrors(): void
    {
        $uploader = new ImageUploader(sys_get_temp_dir());

        $this->assertThrows(
            static fn (): string => $uploader->store([
                'error' => UPLOAD_ERR_INI_SIZE,
                'tmp_name' => '',
                'size' => 0,
                'name' => 'huge.png',
            ], 'avatars'),
            RuntimeException::class,
            'an oversized upload reported by PHP is refused',
        );
    }

    /**
     * A standalone, non-uploaded path must be rejected, which blocks reading
     * arbitrary server files through the upload handler.
     */
    public function testUploaderRejectsFilesThatWereNotUploaded(): void
    {
        $uploader = new ImageUploader(sys_get_temp_dir());
        $path = $this->temporaryFile('not an upload');

        $this->assertThrows(
            static fn (): string => $uploader->store([
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $path,
                'size' => 14,
                'name' => 'x.png',
            ], 'avatars'),
            RuntimeException::class,
            'a path that was not an HTTP upload is refused',
        );
    }

    /**
     * Liking twice must record one like and one increment. This is the fix for
     * the read-then-write race in the previous implementation.
     */
    public function testDuplicateLikeIsCountedOnce(): void
    {
        $database = Bootstrap::seeded();
        $posts = new PostRepository($database);

        $postId = $posts->create(1, 1, 'a post worth liking');

        $first = $posts->like($postId, 2);
        $second = $posts->like($postId, 2);

        $this->assertTrue($first, 'the first like is recorded');
        $this->assertFalse($second, 'the second like is refused');
        $this->assertSame(1, $posts->likeCount($postId), 'the like count is one, not two');
        $this->assertTrue($posts->hasLiked($postId, 2), 'the user is recorded as having liked the post');
    }

    /**
     * Unliking must decrement exactly once and be idempotent.
     */
    public function testUnlikeIsIdempotent(): void
    {
        $database = Bootstrap::seeded();
        $posts = new PostRepository($database);

        $postId = $posts->create(1, 1, 'another post');
        $posts->like($postId, 2);

        $this->assertTrue($posts->unlike($postId, 2), 'the first unlike removes the like');
        $this->assertFalse($posts->unlike($postId, 2), 'a second unlike is a no-op');
        $this->assertSame(0, $posts->likeCount($postId), 'the like count returns to zero');
    }

    /**
     * The like counter must never go below zero even if unlike is called for a
     * user who never liked the post.
     */
    public function testLikeCountCannotGoNegative(): void
    {
        $database = Bootstrap::seeded();
        $posts = new PostRepository($database);

        $postId = $posts->create(1, 1, 'a post nobody liked');
        $posts->unlike($postId, 2);

        $this->assertSame(0, $posts->likeCount($postId), 'like count stays at zero');
    }

    /**
     * Adding a comment must keep the denormalised counter in step.
     */
    public function testCommentCountTracksComments(): void
    {
        $database = Bootstrap::seeded();
        $posts = new PostRepository($database);

        $postId = $posts->create(1, 1, 'a post to discuss');
        $posts->addComment($postId, 2, 'first reply');
        $posts->addComment($postId, 2, 'second reply');

        $row = $database->selectOne('SELECT comment_count FROM posts WHERE id = :id', ['id' => $postId]);

        $this->assertSame(2, (int) $row['comment_count'], 'comment count matches the number of comments');
        $this->assertSame(2, count($posts->comments($postId)), 'both comments are returned');
    }

    /**
     * Deleting a post must remove it from every read path.
     */
    public function testSoftDeletedPostDisappearsFromReads(): void
    {
        $database = Bootstrap::seeded();
        $posts = new PostRepository($database);

        $postId = $posts->create(1, 1, 'a post that will be removed');
        $this->assertTrue($posts->softDelete($postId, 2), 'the post is marked deleted');

        $this->assertSame(null, $posts->find($postId), 'the deleted post is no longer readable');
        $this->assertSame(null, $posts->authorId($postId), 'the deleted post has no author to authorise against');
        $this->assertSame([], $posts->feed(1), 'the deleted post is absent from the feed');
    }

    /**
     * Author identity must survive a rename, which is impossible when posts
     * reference a username string. This is the regression test for that defect.
     */
    public function testPostsSurviveAnAuthorRename(): void
    {
        $database = Bootstrap::seeded();
        $posts = new PostRepository($database);

        $postId = $posts->create(1, 1, 'written before the rename');

        // Rename exactly as the profile page does.
        $database->execute("UPDATE users SET username = 'alice_renamed' WHERE id = 1");

        $post = $posts->find($postId);

        $this->assertTrue($post !== null, 'the post is still found after its author is renamed');
        $this->assertSame(1, (int) $post['author_id'], 'the post still points at the same account');
        $this->assertSame('alice_renamed', (string) $post['username'], 'the post renders the new username');
    }

    /**
     * Writing a value the schema forbids must fail at the database rather than
     * silently storing nonsense.
     */
    public function testDatabaseRejectsInvalidRole(): void
    {
        $database = Bootstrap::seeded();

        $this->assertThrows(
            static fn (): int => $database->insert(
                "INSERT INTO users (username, password_hash, role, status)
                 VALUES ('mallory', 'hash', 'superuser', 'active')",
            ),
            \PDOException::class,
            'an unknown role is rejected by the check constraint',
        );
    }

    /**
     * A like referencing a nonexistent post must be refused by the foreign key,
     * so orphaned rows cannot accumulate.
     */
    public function testDatabaseRejectsOrphanedLike(): void
    {
        $database = Bootstrap::seeded();

        $this->assertThrows(
            static fn (): int => $database->insert(
                'INSERT INTO post_likes (post_id, user_id) VALUES (99999, 1)',
            ),
            \PDOException::class,
            'a like for a missing post is rejected by the foreign key',
        );
    }

    /**
     * A JSON request body must be parsed, since the front-end modules send JSON
     * rather than form encoding. Without this, every field reads as missing.
     */
    public function testJsonBodyIsParsedIntoFields(): void
    {
        $request = Request::create(
            'POST',
            '/api/like',
            ['post_id' => 1],
            [],
            ['content-type' => 'application/json', 'accept' => 'application/json'],
        );

        $this->assertSame(1, $request->input('post_id'), 'a JSON field is readable through input()');
        $this->assertTrue($request->isUnsafe(), 'POST is treated as a state-changing method');
        $this->assertTrue($request->wantsJson(), 'an Accept header of application/json is detected');
    }

    /**
     * A request without a JSON Accept header must not be treated as an API call,
     * so a browser form post still receives an HTML error page.
     */
    public function testHtmlRequestIsNotTreatedAsJson(): void
    {
        $request = Request::create('POST', '/login', ['username' => 'a'], [], ['accept' => 'text/html']);

        $this->assertFalse($request->wantsJson(), 'an HTML Accept header is not detected as JSON');
        $this->assertTrue($request->isUnsafe(), 'form posts are state-changing');
    }

    /**
     * `input()` reads the body first and falls back to the query string, which
     * is the documented behaviour routes rely on.
     */
    public function testInputFallsBackToTheQueryString(): void
    {
        $request = Request::create('GET', '/community', [], ['category' => 'tech']);

        $this->assertSame('tech', $request->query('category'), 'the query parameter is readable via query()');
        $this->assertSame('tech', $request->input('category'), 'input() falls back to the query string');
        $this->assertSame('fallback', $request->input('missing', 'fallback'), 'a default is returned when absent');
    }

    /**
     * A path with a trailing slash must match the same route as one without.
     */
    public function testTrailingSlashIsNormalised(): void
    {
        $this->assertSame('/community', Request::create('GET', '/community/')->path(), 'a trailing slash is stripped');
        $this->assertSame('/', Request::create('GET', '/')->path(), 'the root path is preserved');
    }

    /**
     * Write a temporary file for upload tests.
     *
     * @param string $contents File contents.
     * @return string Path to the created file.
     */
    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dsh-test-');

        if ($path === false) {
            $this->fail('could not create a temporary file');
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
