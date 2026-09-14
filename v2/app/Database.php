<?php
declare(strict_types=1);

namespace JFS;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $driver = (string) ($this->config['driver'] ?? 'mysql');
        try {
            if ($driver === 'sqlite') {
                $path = (string) ($this->config['path'] ?? '');
                if ($path === '') {
                    throw new RuntimeException('SQLite-Pfad fehlt.');
                }
                $this->connection = new PDO('sqlite:' . $path);
                $this->connection->exec('PRAGMA foreign_keys = ON');
            } else {
                $host = (string) ($this->config['host'] ?? 'localhost');
                $port = (int) ($this->config['port'] ?? 3306);
                $name = (string) ($this->config['name'] ?? '');
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
                $this->connection = new PDO($dsn, (string) ($this->config['user'] ?? ''), (string) ($this->config['password'] ?? ''));
            }
        } catch (PDOException $exception) {
            throw new RuntimeException('Die Datenbankverbindung konnte nicht hergestellt werden.', 0, $exception);
        }

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        return $this->connection;
    }
}
