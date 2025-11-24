<?php

namespace App\Models;

class UrlCheck
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create($data)
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

    public function findByUrlId($urlId)
    {
        $stmt = $this->db->prepare('SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC');
        $stmt->execute([$urlId]);
        return $stmt->fetchAll();
    }

    public function getLastCheck($urlId)
    {
        $stmt = $this->db->prepare('SELECT * FROM url_checks WHERE url_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$urlId]);
        return $stmt->fetch();
    }
}