<?php

namespace CRMMarketing;

class Database
{
    private static ?PDO $instance = null;
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function getInstance(string $path): PDO
    {
        if (self::$instance === null) {
            $dsn = 'sqlite:' . $path;
            self::$instance = new \PDO($dsn);
            self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        return self::$instance;
    }

    public function connect(): PDO
    {
        return self::getInstance($this->path);
    }

    public function migrate(string $schema): void
    {
        $pdo = $this->connect();
        $pdo->exec($schema);
    }
}