<?php
require_once 'config/Database.php';

class MedicineController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $action === 'my') {
            $this->myMedicines();
        } elseif ($method === 'GET' && ($action === '' || $action === null)) {
            $this->listMedicines();
        } elseif ($method === 'GET' && is_numeric($action)) {
            $this->getMedicine($action);
        } elseif ($method === 'POST' && ($action === '' || $action === null)) {
            $this->createMedicine();
        } elseif ($method === 'POST' && $action === 'verify') {
            $this->verifyMedicine();
        } elseif ($method === 'POST' && $action === 'update' && $id) {
            $this->updateMedicine($id);
        } elseif ($method === 'DELETE' && $id) {
            $this->deleteMedicine($id);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in medicines"]);
        }
    }

    private function listMedicines() {
        $category = isset($_GET['category']) ? $_GET['category'] : null;
        $nhis = isset($_GET['nhis']) ? $_GET['nhis'] : null;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $offset = ($page - 1) * $limit;
        $agent_id = isset($_GET['agent_id']) ? $_GET['agent_id'] : null;

        $where = [];
        $params = [];

        if ($category && $category !== 'all') {
            $where[] = "m.category = :cat";
            $params['cat'] = $category;
        }
        if ($nhis === 'true') {
            $where[] = "m.nhis_listed = 1";
        }
        if ($search) {
            $where[] = "m.name LIKE :search";
            $params['search'] = "%$search%";
        }
        if ($agent_id) {
            $where[] = "m.agent_id = :agent";
            $params['agent'] = $agent_id;
        }

        // Hide custom invoices from the public store
        $where[] = "m.name NOT LIKE '[Custom Invoice]%'";

        $whereSql = "";
        if (count($where) > 0) {
            $whereSql = "WHERE " . implode(" AND ", $where);
        }

        $query = "SELECT m.*, a.pharmacy_name, a.allow_pay_on_delivery FROM medicines m LEFT JOIN agents a ON m.agent_id = a.id $whereSql ORDER BY m.created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $medicines = $stmt->fetchAll();

        // Convert boolean fields
        foreach($medicines as &$m) {
            $m['fda_approved'] = (bool)$m['fda_approved'];
            $m['nhis_listed'] = (bool)$m['nhis_listed'];
            $m['requires_rx'] = (bool)$m['requires_rx'];
            $m['price'] = (float)$m['price'];
        }

        $countQuery = "SELECT COUNT(*) FROM medicines m $whereSql";
        $stmtCount = $this->conn->prepare($countQuery);
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();

        echo json_encode([
            "success" => true,
            "medicines" => $medicines,
            "total" => (int)$total
        ]);
    }

    private function getMedicine($id) {
        $stmt = $this->conn->prepare("SELECT m.*, a.pharmacy_name, a.allow_pay_on_delivery FROM medicines m LEFT JOIN agents a ON m.agent_id = a.id WHERE m.id = :id");
        $stmt->execute(['id' => $id]);
        $medicine = $stmt->fetch();

        if (!$medicine) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Medicine not found"]);
            return;
        }

        $medicine['fda_approved'] = (bool)$medicine['fda_approved'];
        $medicine['nhis_listed'] = (bool)$medicine['nhis_listed'];
        $medicine['requires_rx'] = (bool)$medicine['requires_rx'];
        $medicine['price'] = (float)$medicine['price'];

        echo json_encode(["success" => true, "medicine" => $medicine]);
    }

    private function verifyMedicine() {
        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $qrCode = isset($input['qrCode']) ? $input['qrCode'] : null;
        $batchNumber = isset($input['batchNumber']) ? $input['batchNumber'] : null;

        $stmt = $this->conn->prepare("SELECT * FROM medicines WHERE qr_code = :qr OR batch_number = :batch LIMIT 1");
        $stmt->execute(['qr' => $qrCode, 'batch' => $batchNumber]);
        $medicine = $stmt->fetch();

        if (!$medicine) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "verified" => false,
                "message" => "Ã¢Å¡Â Ã¯Â¸Â WARNING: This medicine could not be verified. Do not dispense Ã¢â‚¬â€ report to FDA Ghana."
            ]);
            return;
        }

        $expired = false;
        if ($medicine['expiry_date']) {
            $expDate = strtotime($medicine['expiry_date']);
            if ($expDate < time()) {
                $expired = true;
            }
        }

        $medicine['fda_approved'] = (bool)$medicine['fda_approved'];
        $medicine['nhis_listed'] = (bool)$medicine['nhis_listed'];
        $medicine['requires_rx'] = (bool)$medicine['requires_rx'];
        $medicine['price'] = (float)$medicine['price'];

        echo json_encode([
            "success" => true,
            "verified" => true,
            "expired" => $expired,
            "medicine" => $medicine,
            "message" => $expired 
                ? "âš ï¸ Medicine is EXPIRED â€” do not dispense"
                : "âœ… Medicine verified â€” genuine & safe to dispense"
        ]);
    }

    private function myMedicines() {
        require_once 'config/Jwt.php';
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        $role = $decoded['role'];

        if ($role !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Only pharmacies can access their inventory"]);
            return;
        }

        $stmt = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy profile not found."]);
            return;
        }

        $agent_id = $agent['id'];

        $stmt = $this->conn->prepare("SELECT * FROM medicines WHERE agent_id = :aid ORDER BY created_at DESC");
        $stmt->execute(['aid' => $agent_id]);
        $medicines = $stmt->fetchAll();

        foreach ($medicines as &$m) {
            $m['price'] = (float)$m['price'];
        }

        echo json_encode(["success" => true, "medicines" => $medicines]);
    }

    private function createMedicine() {
        require_once 'config/Jwt.php';
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        $role = $decoded['role'];

        if ($role !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Only pharmacies can add inventory"]);
            return;
        }

        // Get the agent_id for this user
        $stmt = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy profile not found. Please complete profile setup."]);
            return;
        }

        $agent_id = $agent['id'];

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $id = "MED-" . time() . "-" . bin2hex(random_bytes(4));
        $name = isset($input['name']) ? $input['name'] : '';
        $category = isset($input['category']) ? $input['category'] : 'other';
        $price = isset($input['price']) ? (float)$input['price'] : 0.00;
        $description = isset($input['description']) ? $input['description'] : '';
        $stock_qty = isset($input['stock_qty']) ? (int)$input['stock_qty'] : 0;
        $expiry_date = isset($input['expiry_date']) && !empty($input['expiry_date']) ? $input['expiry_date'] : null;
        $nhis_listed = isset($input['nhis_listed']) ? (int)$input['nhis_listed'] : 0;

        if (empty($name) || $price <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name and valid price are required"]);
            return;
        }

        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/medicines/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '-' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $image_url = '../backend/uploads/medicines/' . $fileName;
            }
        }

        $query = "INSERT INTO medicines (id, agent_id, name, category, price, description, stock_qty, expiry_date, image_url, nhis_listed) 
                  VALUES (:id, :agent_id, :name, :cat, :price, :desc, :stock, :exp, :image_url, :nhis)";
        $stmt = $this->conn->prepare($query);
        try {
            $stmt->execute([
                'id' => $id,
                'agent_id' => $agent_id,
                'name' => $name,
                'cat' => $category,
                'price' => $price,
                'desc' => $description,
                'stock' => $stock_qty,
                'exp' => $expiry_date,
                'image_url' => $image_url,
                'nhis' => $nhis_listed
            ]);
            echo json_encode(["success" => true, "message" => "Medicine added successfully", "id" => $id]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to add medicine", "error" => $e->getMessage()]);
        }
    }

    private function updateMedicine($id) {
        require_once 'config/Jwt.php';
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        $role = $decoded['role'];

        if ($role !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $stmt = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy profile not found."]);
            return;
        }

        $agent_id = $agent['id'];

        // Verify ownership
        $stmt = $this->conn->prepare("SELECT * FROM medicines WHERE id = :id AND agent_id = :agent_id");
        $stmt->execute(['id' => $id, 'agent_id' => $agent_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized or medicine not found"]);
            return;
        }

        $input = $_POST; // FormData uses POST
        $name = isset($input['name']) ? $input['name'] : '';
        $category = isset($input['category']) ? $input['category'] : 'other';
        $price = isset($input['price']) ? (float)$input['price'] : 0.00;
        $description = isset($input['description']) ? $input['description'] : '';
        $stock_qty = isset($input['stock_qty']) ? (int)$input['stock_qty'] : 0;
        $expiry_date = isset($input['expiry_date']) && !empty($input['expiry_date']) ? $input['expiry_date'] : null;
        $nhis_listed = isset($input['nhis_listed']) ? (int)$input['nhis_listed'] : 0;

        if (empty($name) || $price <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name and valid price are required"]);
            return;
        }

        $query = "UPDATE medicines SET name = :name, category = :cat, price = :price, description = :desc, stock_qty = :stock, expiry_date = :exp, nhis_listed = :nhis";
        $params = [
            'id' => $id,
            'name' => $name,
            'cat' => $category,
            'price' => $price,
            'desc' => $description,
            'stock' => $stock_qty,
            'exp' => $expiry_date,
            'nhis' => $nhis_listed
        ];

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/medicines/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = uniqid() . '-' . basename($_FILES['image']['name']);
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $query .= ", image_url = :image_url";
                $params['image_url'] = '../backend/uploads/medicines/' . $fileName;
            }
        }

        $query .= " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        try {
            $stmt->execute($params);
            echo json_encode(["success" => true, "message" => "Medicine updated successfully"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    }

    private function deleteMedicine($id) {
        require_once 'config/Jwt.php';
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        $role = $decoded['role'];

        if ($role !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $stmt = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $agent = $stmt->fetch();

        if (!$agent) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy profile not found."]);
            return;
        }

        $agent_id = $agent['id'];

        // Verify ownership
        $stmt = $this->conn->prepare("SELECT * FROM medicines WHERE id = :id AND agent_id = :agent_id");
        $stmt->execute(['id' => $id, 'agent_id' => $agent_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized or medicine not found"]);
            return;
        }

        $stmt = $this->conn->prepare("DELETE FROM medicines WHERE id = :id AND agent_id = :agent_id");
        try {
            $stmt->execute(['id' => $id, 'agent_id' => $agent_id]);
            echo json_encode(["success" => true, "message" => "Medicine deleted successfully"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    }
}
?>
