<?php

namespace App\Models;

use Carbon\Carbon;

class Url
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function find($id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByName($name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE name = ?');
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT * FROM urls ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function create($name): int
    {
        $stmt = $this->db->prepare('INSERT INTO urls (name) VALUES (?) RETURNING id');
        $stmt->execute([$name]);
        return $stmt->fetch()['id'];
    }

    public function getAllWithLastCheck(): array
    {
        $sql = "
        SELECT 
            u.id,
            u.name,
            u.created_at,
            uc.status_code,
            uc.created_at as last_check_at
        FROM urls u
        LEFT JOIN LATERAL (
            SELECT status_code, created_at
            FROM url_checks
            WHERE url_id = u.id
            ORDER BY created_at DESC
            LIMIT 1
        ) uc ON true
        ORDER BY u.created_at DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
}
