<?php

namespace App\Models;

use Carbon\Carbon;

class Url
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function find($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByName($name)
    {
        $stmt = $this->db->prepare('SELECT * FROM urls WHERE name = ?');
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public function all()
    {
        $stmt = $this->db->query('SELECT * FROM urls ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function create($name)
    {
        $stmt = $this->db->prepare('INSERT INTO urls (name) VALUES (?) RETURNING id');
        $stmt->execute([$name]);
        return $stmt->fetch()['id'];
    }

    public function getAllWithLastCheck()
    {
    $sql = "
        SELECT 
            u.id,
            u.name,
            u.created_at,
            uc.status_code,
            uc.created_at as last_check_at
        FROM urls u
        LEFT JOIN (
            SELECT DISTINCT ON (url_id) url_id, status_code, created_at
            FROM url_checks
            ORDER BY url_id, created_at DESC
        ) uc ON u.id = uc.url_id
        ORDER BY u.created_at DESC
    ";
    
    $stmt = $this->db->query($sql);
    return $stmt->fetchAll();
    }
}