<?php
class Database {
    private $host = "localhost";
    private $port = "3306";  
    private $db_name = "jimmy_furniture_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}",  // ← ADD PORT HERE
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            return $this->conn;
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return null;
        }
    }
}
?>