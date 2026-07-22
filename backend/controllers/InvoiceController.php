<?php
require_once 'config/Database.php';
require_once 'utils/Jwt.php';

class InvoiceController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            if ($action === 'admin') {
                $this->getAdminInvoices();
            } elseif ($action === 'agent') {
                $this->getAgentInvoices();
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Endpoint not found"]);
            }
        } elseif ($method === 'POST') {
            if ($action === 'generate') {
                $this->generateInvoices();
            } elseif ($action === 'pay' && $id) {
                $this->markAsPaid($id);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Endpoint not found"]);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Method not allowed"]);
        }
    }

    private function generateInvoices() {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $month = isset($input['month']) ? $input['month'] : date('Y-m');

        // Find all cash orders for this month that are marked as paid (i.e. delivered/collected)
        // Group them by agent_id
        $stmt = $this->conn->prepare("
            SELECT agent_id, COUNT(*) as total_orders, SUM(admin_commission) as total_owed
            FROM orders
            WHERE payment_method = 'cash' 
              AND payment_status = 'paid'
              AND DATE_FORMAT(created_at, '%Y-%m') = :month
            GROUP BY agent_id
            HAVING total_owed > 0
        ");
        $stmt->execute(['month' => $month]);
        $agentOwes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $generated = 0;
        foreach ($agentOwes as $row) {
            $agent_id = $row['agent_id'];
            $total_owed = $row['total_owed'];
            $total_orders = $row['total_orders'];

            // Check if invoice already exists for this agent and month
            $checkStmt = $this->conn->prepare("SELECT id FROM invoices WHERE agent_id = :aid AND billing_month = :month");
            $checkStmt->execute(['aid' => $agent_id, 'month' => $month]);
            
            if ($checkStmt->rowCount() === 0) {
                // Create invoice
                $invoice_id = "INV-ID-" . time() . "-" . bin2hex(random_bytes(4));
                $invoice_no = "INV-" . date("Ymd") . "-" . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

                $insertStmt = $this->conn->prepare("
                    INSERT INTO invoices (id, invoice_no, agent_id, billing_month, total_orders, amount_due, status)
                    VALUES (:id, :inv_no, :aid, :month, :orders, :amount, 'unpaid')
                ");
                $insertStmt->execute([
                    'id' => $invoice_id,
                    'inv_no' => $invoice_no,
                    'aid' => $agent_id,
                    'month' => $month,
                    'orders' => $total_orders,
                    'amount' => $total_owed
                ]);
                $generated++;
            }
        }

        echo json_encode(["success" => true, "message" => "$generated invoices generated for $month", "generated" => $generated]);
    }

    private function getAdminInvoices() {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $stmt = $this->conn->query("
            SELECT i.*, a.pharmacy_name, u.email 
            FROM invoices i
            JOIN agents a ON i.agent_id = a.id
            JOIN users u ON a.user_id = u.id
            ORDER BY i.created_at DESC
        ");
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "invoices" => $invoices]);
    }

    private function getAgentInvoices() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        // Get agent id
        $stmtAgent = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
        $stmtAgent->execute(['uid' => $user_id]);
        $agent = $stmtAgent->fetch();

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Agent not found"]);
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT * FROM invoices 
            WHERE agent_id = :aid
            ORDER BY created_at DESC
        ");
        $stmt->execute(['aid' => $agent['id']]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "invoices" => $invoices]);
    }

    private function markAsPaid($invoice_id) {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $stmt = $this->conn->prepare("UPDATE invoices SET status = 'paid' WHERE id = :id");
        $stmt->execute(['id' => $invoice_id]);

        echo json_encode(["success" => true, "message" => "Invoice marked as paid"]);
    }
}
?>
