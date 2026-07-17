<?php
require_once 'config/Database.php';

class PrescriptionController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && $action === 'custom-invoice') {
            $this->createCustomInvoice();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in prescriptions"]);
        }
    }

    private function createCustomInvoice() {
        require_once 'config/Jwt.php';
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        $role = $decoded['role'];

        if ($role !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Only pharmacies can create custom invoices"]);
            return;
        }

        // Get the agent_id for this user
        $stmt = $this->conn->prepare("SELECT id, pharmacy_name FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy profile not found."]);
            return;
        }

        $agent_id = $agent['id'];
        $pharmacy_name = $agent['pharmacy_name'];

        $input = json_decode(file_get_contents("php://input"), true);
        
        $patient_id = isset($input['patient_id']) ? $input['patient_id'] : '';
        $item_name = isset($input['item_name']) ? $input['item_name'] : '';
        $price = isset($input['price']) ? (float)$input['price'] : 0.00;
        $prescription = isset($input['prescription']) ? $input['prescription'] : '';

        if (empty($item_name) || $price <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Item name and valid price are required"]);
            return;
        }

        // Create a custom medicine record
        $medicine_id = "MED-" . time() . "-" . bin2hex(random_bytes(4));
        $db_name = "[Custom Invoice] " . $item_name;
        
        $query = "INSERT INTO medicines (id, agent_id, name, category, price, description, stock_qty) 
                  VALUES (:id, :agent_id, :name, 'other', :price, :description, 1)";
        $stmt = $this->conn->prepare($query);
        $result = $stmt->execute([
            'id' => $medicine_id,
            'agent_id' => $agent_id,
            'name' => $db_name,
            'price' => $price,
            'description' => $prescription
        ]);

        if ($result) {
            $payload = [
                "type" => "invoice",
                "name" => $item_name,
                "prescription" => $prescription,
                "price" => $price,
                "medicine_id" => $medicine_id,
                "agent_id" => $agent_id,
                "agent_name" => $pharmacy_name
            ];
            
            http_response_code(201);
            echo json_encode([
                "success" => true, 
                "message" => "Custom invoice created successfully",
                "invoice" => [
                    "payload" => $payload
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to create invoice."]);
        }
    }
}
?>
