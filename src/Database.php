<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $databaseUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        
        if (!$databaseUrl) {
            die("DATABASE_URL environment variable is not set");
        }
        
        $databaseUrl = parse_url($databaseUrl);
        
        if (!$databaseUrl) {
            die("Invalid DATABASE_URL format");
        }
        
        $username = $databaseUrl['user'] ?? '';
        $password = $databaseUrl['pass'] ?? '';
        $host = $databaseUrl['host'] ?? '';
        $port = $databaseUrl['port'] ?? '5432';
        $dbName = ltrim($databaseUrl['path'] ?? '', '/');

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbName}";

        if (getenv('RENDER')) {
            $dsn .= ";sslmode=require";
        }

        try {
            $this->connection = new PDO($dsn, $username, $password);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}