<?php

namespace App\Models;

use PDO;

class UrlCheck
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO url_checks (url_id, status_code, h1, title, description) VALUES (?, ?, ?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['url_id'],
            $data['status_code'],
            $data['h1'] ?? null,
            $data['title'] ?? null,
            $data['description'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function findByUrlId(int $urlId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC');
        $stmt->execute([$urlId]);
        return $stmt->fetchAll();
    }

    public function getLastCheck(int $urlId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$urlId]);
        return $stmt->fetch();
    }
}
