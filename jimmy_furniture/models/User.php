<?php
// models/User.php
class User {
    private $conn;
    private $table = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function register($name, $email, $password) {
        // Check if email already exists
        $checkStmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE email = :email");
        $checkStmt->execute([':email' => $email]);
        
        if ($checkStmt->fetch()) {
            return false; // Email already exists
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table} (name, email, password, role) VALUES (:name, :email, :password, 'user')"
        );
        
        return $stmt->execute([
            ":name" => $name,
            ":email" => $email,
            ":password" => $hash
        ]);
    }

    public function login($email, $password) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1"
        );
        $stmt->execute([":email" => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']); // Remove password from result
            return $user;
        }
        return false;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT id, name, email, role FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute([":id" => $id]);
        return $stmt->fetch();
    }

    // Admin functions
    public function getAllUsers($limit = null, $offset = 0) {
        $query = "SELECT id, name, email, role, created_at FROM {$this->table} ORDER BY created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($limit) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateUserRole($id, $role) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table} 
            SET role = :role 
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':role' => $role
        ]);
    }

    public function deleteUser($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function getUserStats() {
        $stmt = $this->conn->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>