<?php
// models/Order.php
class Order {
    private $conn;
    private $table = "orders";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create new order
    public function create($user_id, $total_amount, $shipping_address, $payment_method) {
        $order_number = 'JF-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        $this->conn->beginTransaction();
        
        try {
            // Create order
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->table} 
                (order_number, user_id, total_amount, shipping_address, payment_method)
                VALUES (:order_number, :user_id, :total_amount, :shipping_address, :payment_method)
            ");
            
            $stmt->execute([
                ':order_number' => $order_number,
                ':user_id' => $user_id,
                ':total_amount' => $total_amount,
                ':shipping_address' => $shipping_address,
                ':payment_method' => $payment_method
            ]);
            
            $order_id = $this->conn->lastInsertId();
            
            return [
                'success' => true,
                'order_id' => $order_id,
                'order_number' => $order_number
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get user's orders
    public function getUserOrders($user_id, $limit = null, $offset = 0) {
        $query = "
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
                   (SELECT SUM(quantity * price) FROM order_items WHERE order_id = o.id) as subtotal
            FROM {$this->table} o
            WHERE o.user_id = :user_id
            ORDER BY o.created_at DESC
        ";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($limit) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get order details with items
    public function getOrderDetails($order_id, $user_id = null) {
        $query = "
            SELECT o.*, 
                   oi.product_id, oi.quantity as item_quantity, oi.price as item_price,
                   p.name as product_name, p.image_url,
                   u.name as user_name, u.email as user_email
            FROM {$this->table} o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = :order_id
        ";
        
        if ($user_id) {
            $query .= " AND o.user_id = :user_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $params = [':order_id' => $order_id];
        if ($user_id) {
            $params[':user_id'] = $user_id;
        }
        
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return null;
        }
        
        // Structure the data
        $order = [
            'order_info' => [
                'id' => $result[0]['id'],
                'order_number' => $result[0]['order_number'],
                'total_amount' => $result[0]['total_amount'],
                'status' => $result[0]['status'],
                'shipping_address' => $result[0]['shipping_address'],
                'payment_method' => $result[0]['payment_method'],
                'created_at' => $result[0]['created_at'],
                'user_name' => $result[0]['user_name'],
                'user_email' => $result[0]['user_email']
            ],
            'items' => []
        ];
        
        foreach ($result as $row) {
            if ($row['product_id']) {
                $order['items'][] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'image_url' => $row['image_url'],
                    'quantity' => $row['item_quantity'],
                    'price' => $row['item_price'],
                    'subtotal' => $row['item_quantity'] * $row['item_price']
                ];
            }
        }
        
        return $order;
    }

    // Get all orders (admin)
    public function getAllOrders($filters = [], $limit = null, $offset = 0) {
        $query = "
            SELECT o.*, u.name as user_name, u.email,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
            FROM {$this->table} o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query .= " AND o.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (o.order_number LIKE :search OR u.name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(o.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(o.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $query .= " ORDER BY o.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = (int)$limit;
            $params[':offset'] = (int)$offset;
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update order status (admin only)
    public function updateStatus($order_id, $status) {
        $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (!in_array($status, $allowed_statuses)) {
            return false;
        }
        
        $stmt = $this->conn->prepare("
            UPDATE {$this->table} 
            SET status = :status 
            WHERE id = :order_id
        ");
        
        return $stmt->execute([
            ':order_id' => $order_id,
            ':status' => $status
        ]);
    }

    // Get order statistics
    public function getStats($period = 'month') {
        $dateFormat = '';
        switch ($period) {
            case 'day': $dateFormat = '%Y-%m-%d'; break;
            case 'week': $dateFormat = '%Y-%W'; break;
            case 'month': $dateFormat = '%Y-%m'; break;
            case 'year': $dateFormat = '%Y'; break;
        }
        
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(created_at, :date_format) as period,
                COUNT(*) as order_count,
                SUM(total_amount) as total_revenue
            FROM {$this->table}
            WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            GROUP BY period
            ORDER BY period DESC
        ");
        
        $stmt->execute([':date_format' => $dateFormat]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add order item
    public function addOrderItem($order_id, $product_id, $quantity, $price) {
        $stmt = $this->conn->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (:order_id, :product_id, :quantity, :price)
        ");
        
        return $stmt->execute([
            ':order_id' => $order_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':price' => $price
        ]);
    }

    // Get recent orders
    public function getRecentOrders($limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT o.*, u.name as user_name
            FROM {$this->table} o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.created_at DESC
            LIMIT :limit
        ");
        
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get order count by status
    public function getCountByStatus() {
        $stmt = $this->conn->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as revenue
            FROM {$this->table}
            GROUP BY status
            ORDER BY FIELD(status, 'pending', 'processing', 'shipped', 'delivered', 'cancelled')
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>