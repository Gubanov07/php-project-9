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
}