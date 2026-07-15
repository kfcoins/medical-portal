<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';

class PrescriptionController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $action === 'search-patient') {
            $this->searchPatient();
        } elseif ($method === 'POST' && $action === 'custom-invoice') {
            $this->createCustomInvoice();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in prescriptions"]);
        }
    }

    private function searchPatient() {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        if (strlen($q) < 3) {
            echo json_encode(["success" => true, "patients" => []]);
            return;
        }

        $stmt = $this->conn->prepare("
            SELECT id, first_name, last_name, email, phone 
            FROM users 
            WHERE role = 'patient' 
            AND (phone LIKE :q OR email LIKE :q OR CONCAT(first_name, ' ', last_name) LIKE :q) 
            LIMIT 10
        ");
        $stmt->execute(['q' => "%$q%"]);
        $patients = $stmt->fetchAll();

        echo json_encode(["success" => true, "patients" => $patients]);
    }

    private function createCustomInvoice() {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $patient_id = $input['patient_id'] ?? null;
        $item_name = $input['item_name'] ?? null;
        $price = isset($input['price']) ? (float)$input['price'] : 0.00;
        $prescription = $input['prescription'] ?? '';

        if (!$patient_id || !$item_name || $price <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required fields or invalid price"]);
            return;
        }

        // Get pharmacy agent details
        $stmtAgent = $this->conn->prepare("SELECT id, pharmacy_name FROM agents WHERE user_id = :uid");
        $stmtAgent->execute(['uid' => $decoded['id']]);
        $agent = $stmtAgent->fetch();

        if (!$agent) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy profile not found"]);
            return;
        }

        $this->conn->beginTransaction();
        try {
            // 1. Create custom medicine entry
            $medicine_id = "MED-" . time() . "-" . bin2hex(random_bytes(4));
            $custom_name = "[Custom Invoice] " . $item_name;
            
            $stmtMed = $this->conn->prepare("
                INSERT INTO medicines (id, agent_id, name, generic_name, category, description, price, stock_qty, requires_rx) 
                VALUES (:id, :aid, :name, :gname, :cat, :desc, :price, :qty, :rx)
            ");
            $stmtMed->execute([
                'id' => $medicine_id,
                'aid' => $agent['id'],
                'name' => $custom_name,
                'gname' => $item_name,
                'cat' => 'other',
                'desc' => $prescription,
                'price' => $price,
                'qty' => 1,
                'rx' => 1
            ]);

            $payload = [
                'type' => 'invoice',
                'medicine_id' => $medicine_id,
                'name' => $item_name,
                'price' => $price,
                'prescription' => $prescription,
                'agent_id' => $agent['id'],
                'agent_name' => $agent['pharmacy_name']
            ];

            $this->conn->commit();
            
            echo json_encode([
                "success" => true, 
                "message" => "Invoice generated successfully",
                "invoice" => [
                    "payload" => $payload
                ]
            ]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to create invoice: " . $e->getMessage()]);
        }
    }
}
?>
