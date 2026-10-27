<?php

declare(strict_types=1);

namespace Core;

final class DbSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public function __construct(private Database $db)
    {
    }

    private function cutoff(): int
    {
        return time() - (int) Config::get('session.idle_timeout', 3600);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $payload = $this->db->fetchColumn(
            'SELECT payload FROM sessions WHERE id = ? AND last_activity > ?',
            [$id, $this->cutoff()]
        );

        return is_string($payload) ? $payload : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = isset($_SESSION['_uid']) && is_int($_SESSION['_uid']) ? $_SESSION['_uid'] : null;
        $now = time();
        $this->db->query(
            'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip_address = VALUES(ip_address),
                 user_agent = VALUES(user_agent), payload = VALUES(payload), last_activity = VALUES(last_activity)',
            [
                $id,
                $userId,
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                $data,
                $now,
                $now,
            ]
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->delete('sessions', ['id' => $id]);

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->db->query('DELETE FROM sessions WHERE last_activity < ?', [$this->cutoff()])->rowCount();
    }

    public function validateId(string $id): bool
    {
        return $this->db->fetchColumn(
            'SELECT 1 FROM sessions WHERE id = ? AND last_activity > ?',
            [$id, $this->cutoff()]
        ) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->db->update('sessions', ['last_activity' => time()], ['id' => $id]);

        return true;
    }
}