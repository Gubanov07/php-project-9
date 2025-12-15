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
            MAX(uc.created_at) as last_check_at,
            -- Берем статус код из последней проверки
            (
                SELECT uc2.status_code 
                FROM url_checks uc2 
                WHERE uc2.url_id = u.id 
                ORDER BY uc2.created_at DESC 
                LIMIT 1
            ) as status_code
        FROM urls u
        LEFT JOIN url_checks uc ON u.id = uc.url_id
        GROUP BY u.id, u.name, u.created_at
        ORDER BY u.id DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
