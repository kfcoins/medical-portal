<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';

class PatientController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $action === 'dashboard' && $id === 'stats') { // api/patient/dashboard/stats
            $this->dashboardStats();
        } elseif ($method === 'GET' && $action === 'prescriptions' && !$id) { // api/patient/prescriptions
            $this->myPrescriptions();
        } elseif ($method === 'POST' && $action === 'prescriptions' && $id === 'upload') { // api/patient/prescriptions/upload
            $this->uploadPrescription();
        } elseif (($method === 'PUT' || $method === 'POST') && $action === 'profile') { // api/patient/profile
            $this->updateProfile();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in patient"]);
        }
    }

    private function dashboardStats() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        // Get total orders
        $stmtTotal = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE patient_id = :uid");
        $stmtTotal->execute(['uid' => $user_id]);
        $totalOrders = $stmtTotal->fetchColumn();



        // Get delivered orders
        $stmtDel = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE patient_id = :uid AND status = 'delivered'");
        $stmtDel->execute(['uid' => $user_id]);
        $deliveredOrders = $stmtDel->fetchColumn();

        // Get recent orders (limit 5)
        $stmtRecent = $this->conn->prepare("
            SELECT o.id, o.amount_due, o.status, o.created_at, a.pharmacy_name
            FROM orders o
            LEFT JOIN agents a ON o.agent_id = a.id
            WHERE o.patient_id = :uid
            ORDER BY o.created_at DESC
            LIMIT 5
        ");
        $stmtRecent->execute(['uid' => $user_id]);
        $recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "stats" => [
                "totalOrders" => (int)$totalOrders,
                "deliveredOrders" => (int)$deliveredOrders
            ],
            "recentOrders" => $recentOrders
        ]);
    }

    private function myPrescriptions() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $stmt = $this->conn->prepare("
            SELECT o.*, a.pharmacy_name, a.region as pharmacy_region
            FROM orders o
            LEFT JOIN agents a ON o.agent_id = a.id
            WHERE o.patient_id = :uid AND o.prescription IS NOT NULL
            ORDER BY o.created_at DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "prescriptions" => $prescriptions
        ]);
    }

    private function uploadPrescription() {
        $decoded = Jwt::authenticate();
        $patient_id = $decoded['id'];

        $agent_id = isset($_POST['agent_id']) ? $_POST['agent_id'] : null;
        $notes = isset($_POST['notes']) ? $_POST['notes'] : null;

        if (!$agent_id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Please select a pharmacy."]);
            return;
        }

        if (!isset($_FILES['prescription_file']) || $_FILES['prescription_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Please upload a valid prescription image or PDF."]);
            return;
        }

        $uploadDir = __DIR__ . '/../../uploads/prescriptions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['prescription_file']['tmp_name'];
        $fileName = $_FILES['prescription_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Only JPG, PNG and PDF files are allowed."]);
            return;
        }

        $newFileName = "rx_" . time() . "_" . uniqid() . "." . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $prescriptionUrl = 'uploads/prescriptions/' . $newFileName;

            try {
                $this->conn->beginTransaction();

                $stmtCount = $this->conn->query("SELECT COUNT(*) FROM orders");
                $count = $stmtCount->fetchColumn();
                $orderNo = "ORD-" . time() . "-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                $order_id = "ORD-ID-" . time() . "-" . bin2hex(random_bytes(4));

                $stmt = $this->conn->prepare("INSERT INTO orders (id, order_no, patient_id, agent_id, total_amount, amount_due, prescription, notes, status) VALUES (:id, :on, :pid, :aid, 0, 0, :rx, :notes, 'pending')");
                $stmt->execute([
                    'id' => $order_id,
                    'on' => $orderNo,
                    'pid' => $patient_id,
                    'aid' => $agent_id,
                    'rx' => $prescriptionUrl,
                    'notes' => $notes
                ]);

                $this->conn->commit();

                // Fetch full order to return
                $stmtGet = $this->conn->prepare("SELECT o.*, a.pharmacy_name FROM orders o LEFT JOIN agents a ON o.agent_id = a.id WHERE o.id = :id");
                $stmtGet->execute(['id' => $order_id]);
                $prescriptionOrder = $stmtGet->fetch(PDO::FETCH_ASSOC);

                http_response_code(201);
                echo json_encode(["success" => true, "message" => "Prescription uploaded successfully.", "prescription" => $prescriptionOrder]);
            } catch (Exception $e) {
                $this->conn->rollBack();
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to move uploaded file."]);
        }
    }

    private function updateProfile() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) {
            $input = $_POST;
        }

        if (empty($input) && empty($_FILES)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "No data provided."]);
            return;
        }

        try {
            $this->conn->beginTransaction();

            // Update basic info and NHIS if provided
            if (isset($input['firstName']) || isset($input['lastName']) || isset($input['phone']) || isset($input['nhis_number']) || isset($_FILES['nhis_card_image'])) {
                $updates = [];
                $params = ['id' => $user_id];
                
                if (isset($input['firstName'])) {
                    $updates[] = "first_name = :fn";
                    $params['fn'] = $input['firstName'];
                }
                if (isset($input['lastName'])) {
                    $updates[] = "last_name = :ln";
                    $params['ln'] = $input['lastName'];
                }
                if (isset($input['phone'])) {
                    $updates[] = "phone = :phone";
                    $params['phone'] = $input['phone'];
                }
                if (isset($input['nhis_number'])) {
                    $updates[] = "nhis_number = :nhis_num";
                    $updates[] = "nhis_status = 'pending'";
                    $params['nhis_num'] = $input['nhis_number'];
                }
                
                if (isset($_FILES['nhis_card_image']) && $_FILES['nhis_card_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/nhis/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $fileName = "nhis_" . $user_id . "_" . time() . "_" . basename($_FILES['nhis_card_image']['name']);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['nhis_card_image']['tmp_name'], $targetPath)) {
                        $updates[] = "nhis_card_url = :nhis_img";
                        $updates[] = "nhis_status = 'pending'";
                        $params['nhis_img'] = 'uploads/nhis/' . $fileName;
                    }
                }

                if (count($updates) > 0) {
                    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = :id";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute($params);
                }
            }

            // Update password if provided
            if (!empty($input['currentPassword']) && !empty($input['newPassword'])) {
                $stmt = $this->conn->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->execute(['id' => $user_id]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($input['currentPassword'], $user['password'])) {
                    throw new Exception("Incorrect current password.");
                }

                $newHash = password_hash($input['newPassword'], PASSWORD_DEFAULT);
                $stmtPass = $this->conn->prepare("UPDATE users SET password = :pass WHERE id = :id");
                $stmtPass->execute(['pass' => $newHash, 'id' => $user_id]);
            }

            $this->conn->commit();

            // Fetch updated user to return (without password)
            $stmtGet = $this->conn->prepare("SELECT id, email, first_name, last_name, phone, role, nhis_number, nhis_card_url, nhis_status FROM users WHERE id = :id");
            $stmtGet->execute(['id' => $user_id]);
            $updatedUser = $stmtGet->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true, 
                "message" => "Profile updated successfully.", 
                "user" => $updatedUser
            ]);

        } catch (Exception $e) {
            $this->conn->rollBack();
            http_response_code(400);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
?>
