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
      $urls = $this->all();
        
        if (empty($urls)) {
            return [];
        }
        
        $urlIds = array_column($urls, 'id');
        $placeholders = implode(',', array_fill(0, count($urlIds), '?'));
        
        $sql = "
            SELECT DISTINCT ON (url_id) 
                url_id, 
                status_code, 
                created_at as last_check_at
            FROM url_checks
            WHERE url_id IN ({$placeholders})
            ORDER BY url_id, created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($urlIds);
        $lastChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $checksMap = [];
        foreach ($lastChecks as $check) {
            $checksMap[$check['url_id']] = [
                'status_code' => $check['status_code'],
                'last_check_at' => $check['last_check_at']
            ];
        }

        foreach ($urls as &$url) {
            $url['status_code'] = $checksMap[$url['id']]['status_code'] ?? null;
            $url['last_check_at'] = $checksMap[$url['id']]['last_check_at'] ?? null;
        }
        
        return $urls;
    }
}
