<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use App\Security\Crypto;

/**
 * Data access for private notes, which are stored encrypted.
 *
 * Encryption and decryption happen here rather than in the service, so a caller
 * cannot accidentally persist plaintext by writing a statement of its own, and
 * no other part of the application ever sees ciphertext.
 *
 * The key derivation lives in {@see Crypto}; the database never holds the key,
 * so a dump of this table alone reveals nothing.
 */
final class NoteRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly Crypto $crypto,
    ) {
    }

    /**
     * Store a new note.
     *
     * @param int $userId Owner identifier.
     * @param string $content Plaintext note body.
     * @return int The new note identifier.
     */
    public function create(int $userId, string $content): int
    {
        $sealed = $this->crypto->encrypt($content);

        return $this->database->insert(
            'INSERT INTO private_notes (user_id, ciphertext, nonce) VALUES (:user_id, :ciphertext, :nonce)',
            [
                'user_id' => $userId,
                'ciphertext' => $sealed['ciphertext'],
                'nonce' => $sealed['nonce'],
            ],
        );
    }

    /**
     * List a user's notes, newest first, with their bodies decrypted.
     *
     * A note that cannot be decrypted is returned with an explicit placeholder
     * rather than being dropped or crashing the page: that happens when the key
     * was rotated or the row was altered, and the user needs to see that the
     * note exists instead of silently losing it.
     *
     * @param int $userId Owner identifier.
     * @param int $limit Maximum rows to return.
     * @return list<array{id: int, content: string, created_at: string, readable: bool}> Note rows.
     */
    public function forUser(int $userId, int $limit = 100): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, ciphertext, nonce, created_at
             FROM private_notes
             WHERE user_id = :user_id AND is_deleted = 0
             ORDER BY created_at DESC, id DESC
             LIMIT :limit',
        );
        $statement->bindValue(':user_id', $userId);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        $notes = [];

        foreach ($rows as $row) {
            $readable = true;

            try {
                $content = $this->crypto->decrypt((string) $row['ciphertext'], (string) $row['nonce']);
            } catch (\Throwable $throwable) {
                $content = '（此笔记无法解密：密钥可能已更换，或数据被修改。）';
                $readable = false;
                error_log(sprintf('[NOTES] Failed to decrypt note #%d: %s', (int) $row['id'], $throwable->getMessage()));
            }

            $notes[] = [
                'id' => (int) $row['id'],
                'content' => $content,
                'created_at' => (string) $row['created_at'],
                'readable' => $readable,
            ];
        }

        return $notes;
    }

    /**
     * Count a user's notes.
     *
     * @param int $userId Owner identifier.
     * @return int Number of notes.
     */
    public function countForUser(int $userId): int
    {
        $row = $this->database->selectOne(
            'SELECT COUNT(*) AS total FROM private_notes WHERE user_id = :user_id AND is_deleted = 0',
            ['user_id' => $userId],
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Soft-delete one of the user's own notes.
     *
     * The owner is part of the statement's condition, so a crafted identifier
     * belonging to somebody else matches no row instead of being trusted.
     *
     * @param int $noteId Note identifier.
     * @param int $userId Owner identifier.
     * @return bool True when a note was removed.
     */
    public function delete(int $noteId, int $userId): bool
    {
        return $this->database->execute(
            'UPDATE private_notes SET is_deleted = 1 WHERE id = :id AND user_id = :user_id AND is_deleted = 0',
            ['id' => $noteId, 'user_id' => $userId],
        ) > 0;
    }
}
