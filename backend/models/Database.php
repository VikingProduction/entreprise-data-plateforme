<?php
/**
 * Classe de gestion de la base de données
 * Singleton pattern pour la connexion MySQL
 */

class Database 
{
    private static $instance = null;
    private $connection;

    private function __construct() 
    {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion DB: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données");
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

    public function query(string $sql, array $params = []): PDOStatement 
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    public function lastInsertId(): string 
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): bool 
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool 
    {
        return $this->connection->commit();
    }

    public function rollback(): bool 
    {
        return $this->connection->rollback();
    }
}
