<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';

class AgentController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && ($action === '' || $action === null)) {
            $this->listAgents();
        } elseif ($method === 'GET' && $action === 'dashboard' && $id === 'stats') { // api/agents/dashboard/stats
            $this->dashboardStats();
        } elseif ($method === 'GET' && $action === 'analytics') { // api/agents/analytics
            $this->getAnalytics();
        } elseif ($method === 'GET' && is_numeric($action)) { // api/agents/:id
            $this->getAgent($action);
        } elseif ($method === 'PUT' && $action === 'profile') {
            $this->updateProfile();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in agents"]);
        }
    }

    private function listAgents() {
        $region = isset($_GET['region']) ? $_GET['region'] : null;
        $agentType = isset($_GET['agentType']) ? $_GET['agentType'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $offset = ($page - 1) * $limit;

        $where = ["a.verification_status = 'approved'"];
        $params = [];

        if ($region) {
            $where[] = "a.region = :region";
            $params['region'] = $region;
        }
        if ($agentType) {
            $where[] = "a.agent_type = :type";
            $params['type'] = $agentType;
        }

        $whereSql = "WHERE " . implode(" AND ", $where);

        $query = "SELECT a.*, u.first_name, u.last_name, u.phone 
                  FROM agents a 
                  JOIN users u ON a.user_id = u.id 
                  $whereSql 
                  ORDER BY a.rating DESC, a.total_orders DESC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $agents = $stmt->fetchAll();

        // count
        $countQuery = "SELECT COUNT(*) FROM agents a $whereSql";
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();

        echo json_encode([
            "success" => true,
            "agents" => $agents,
            "total" => $total,
            "page" => $page,
            "pages" => ceil($total / $limit)
        ]);
    }

    private function getAgent($id) {
        $query = "SELECT a.*, u.first_name, u.last_name, u.phone, u.email 
                  FROM agents a 
                  JOIN users u ON a.user_id = u.id 
                  WHERE a.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Agent not found"]);
            return;
        }

        echo json_encode(["success" => true, "agent" => $agent]);
    }

    private function updateProfile() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $updates = [];
        $params = ['uid' => $user_id];
        $allowedFields = ['pharmacy_name', 'council_reg_no', 'fda_license_no', 'agent_type', 'region', 'address', 'bio', 'nhis_enabled'];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = :$field";
                $params[$field] = $input[$field];
            }
        }

        if (count($updates) === 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "No valid fields to update"]);
            return;
        }

        $updateSql = implode(", ", $updates);
        $query = "UPDATE agents SET $updateSql WHERE user_id = :uid";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        // Fetch updated
        $stmt = $this->conn->prepare("SELECT * FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        echo json_encode(["success" => true, "agent" => $agent]);
    }

    private function dashboardStats() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $stmt = $this->conn->prepare("SELECT id, rating, verification_status FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Agent not found"]);
            return;
        }

        $agent_id = $agent['id'];

        // Total orders
        $stmtTotal = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE agent_id = :aid");
        $stmtTotal->execute(['aid' => $agent_id]);
        $totalOrders = $stmtTotal->fetchColumn();

        // Delivered orders
        $stmtDel = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE agent_id = :aid AND status = 'delivered'");
        $stmtDel->execute(['aid' => $agent_id]);
        $delivered = $stmtDel->fetchColumn();

        // Revenue
        $stmtRev = $this->conn->prepare("SELECT SUM(amount_due) FROM orders WHERE agent_id = :aid AND payment_status = 'paid'");
        $stmtRev->execute(['aid' => $agent_id]);
        $revenue = $stmtRev->fetchColumn();

        // Products in stock
        $stmtStock = $this->conn->prepare("SELECT COUNT(*) FROM medicines WHERE agent_id = :aid");
        $stmtStock->execute(['aid' => $agent_id]);
        $productsInStock = $stmtStock->fetchColumn();



        echo json_encode([
            "success" => true,
            "stats" => [
                "totalOrders" => (int)$totalOrders,
                "delivered" => (int)$delivered,
                "revenue" => (float)$revenue,
                "rating" => (float)$agent['rating'],
                "verificationStatus" => $agent['verification_status'],
                "productsInStock" => (int)$productsInStock
            ]
        ]);
    }

    private function getAnalytics() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $stmt = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Agent not found"]);
            return;
        }

        $agent_id = $agent['id'];

        // Get total orders by status
        $stmtStatus = $this->conn->prepare("SELECT status, COUNT(*) as count FROM orders WHERE agent_id = :aid GROUP BY status");
        $stmtStatus->execute(['aid' => $agent_id]);
        $ordersByStatus = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

        // Get revenue by month (last 6 months)
        // Adjust for MySQL vs SQLite date formatting. Assuming MySQL (XAMPP):
        $stmtRev = $this->conn->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount_due) as total_revenue
            FROM orders
            WHERE agent_id = :aid AND payment_status = 'paid'
            GROUP BY month
            ORDER BY month DESC
            LIMIT 6
        ");
        $stmtRev->execute(['aid' => $agent_id]);
        $revenueByMonth = array_reverse($stmtRev->fetchAll(PDO::FETCH_ASSOC));

        // Get top 5 selling medicines
        $stmtTop = $this->conn->prepare("
            SELECT m.name, SUM(oi.quantity) as total_sold
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN medicines m ON oi.medicine_id = m.id
            WHERE o.agent_id = :aid AND o.payment_status = 'paid'
            GROUP BY oi.medicine_id
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        $stmtTop->execute(['aid' => $agent_id]);
        $topMedicines = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "analytics" => [
                "ordersByStatus" => $ordersByStatus,
                "revenueByMonth" => $revenueByMonth,
                "topMedicines" => $topMedicines
            ]
        ]);
    }
}
?>
