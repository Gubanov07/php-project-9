<?php

namespace App\Models;

use Carbon\Carbon;
use PDO;

class Url
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE name = ?');
        $stmt->execute([$name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM urls ORDER BY created_at DESC');
        $result = $stmt->fetchAll();
        return $result ?: [];
    }

    public function create(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO urls (name) VALUES (?) RETURNING id');
        $stmt->execute([$name]);
        $result = $stmt->fetch();
        return (int) ($result['id'] ?? 0);
    }

    public function getAllWithLastCheck(): array
    {
        $sql = "
        SELECT
            u.id,
            u.name,
            u.created_at,
            (
                SELECT status_code
                FROM url_checks
                WHERE url_id = u.id
                ORDER BY created_at DESC
                LIMIT 1
            ) as status_code,
            (
                SELECT created_at
                FROM url_checks
                WHERE url_id = u.id
                ORDER BY created_at DESC
                LIMIT 1
            ) as last_check_at
        FROM urls u
        ORDER BY u.created_at DESC
    ";

        $stmt = $this->db->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result ?: [];
    }
}
