<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';
require_once 'utils/Mailer.php';

class AdminController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        // Authenticate all admin routes
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized access. Admin only."]);
            return;
        }
        $this->user = (object)$decoded;

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $action === 'pending-pharmacies') {
            $this->getPendingPharmacies();
        } elseif ($method === 'POST' && $action === 'approve-pharmacy' && $id) {
            $this->approvePharmacy($id);
        } elseif ($method === 'POST' && $action === 'reject-pharmacy' && $id) {
            $this->rejectPharmacy($id);
        } elseif ($method === 'GET' && $action === 'pharmacies' && !$id) {
            $this->getAllPharmacies();
        } elseif ($method === 'GET' && $action === 'patients' && !$id) {
            $this->getAllPatients();
        } elseif ($method === 'PUT' && $action === 'profile' && !$id) {
            $this->updateProfile();
        } elseif ($method === 'POST' && $action === 'pharmacies' && strpos($id, '/toggle') !== false) {
            $agentId = explode('/', $id)[0];
            $this->togglePharmacyStatus($agentId);
        } elseif ($method === 'GET' && $action === 'stats') {
            $this->getStats();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in admin"]);
        }
    }

    private function getPendingPharmacies() {
        $stmt = $this->conn->prepare("
            SELECT a.id as agent_table_id, a.agent_id, a.pharmacy_name, a.council_reg_no, a.fda_license_no, 
                   a.agent_type, a.region, a.bio, a.verification_status, a.created_at, a.id_front_url, a.id_back_url, a.pharm_license_url,
                   u.id as user_id, u.first_name, u.last_name, u.email, u.phone, u.ghana_card
            FROM agents a
            JOIN users u ON a.user_id = u.id
            ORDER BY 
                CASE a.verification_status 
                    WHEN 'pending' THEN 1 
                    WHEN 'approved' THEN 2 
                    WHEN 'rejected' THEN 3 
                    ELSE 4 
                END, 
                a.created_at DESC
        ");
        $stmt->execute();
        $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $pharmacies]);
    }

    private function getStats() {
        // Total Users
        $stmt = $this->conn->query("SELECT COUNT(*) FROM users WHERE role = 'patient'");
        $totalPatients = $stmt->fetchColumn();

        // Active Pharmacies
        $stmt = $this->conn->query("SELECT COUNT(*) FROM agents WHERE verification_status = 'approved'");
        $activePharmacies = $stmt->fetchColumn();

        // Pending Pharmacies
        $stmt = $this->conn->query("SELECT COUNT(*) FROM agents WHERE verification_status = 'pending'");
        $pendingPharmacies = $stmt->fetchColumn();

        echo json_encode([
            "success" => true, 
            "data" => [
                "patients" => $totalPatients,
                "activePharmacies" => $activePharmacies,
                "pendingPharmacies" => $pendingPharmacies
            ]
        ]);
    }

    private function approvePharmacy($id) {
        $stmt = $this->conn->prepare("SELECT a.*, u.email, u.first_name FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = :id");
        $stmt->execute(['id' => $id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Pharmacy not found"]);
            return;
        }

        if ($agent['verification_status'] !== 'pending' && $agent['verification_status'] !== 'rejected') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy cannot be approved from its current state"]);
            return;
        }

        $stmtUpdate = $this->conn->prepare("UPDATE agents SET verification_status = 'approved' WHERE id = :id");
        $stmtUpdate->execute(['id' => $id]);

        $mailer = new Mailer();
        $pharmacyName = $agent['pharmacy_name'] ? $agent['pharmacy_name'] : $agent['first_name'];
        $mailer->sendPharmacyApproved($agent['email'], $pharmacyName);

        echo json_encode(["success" => true, "message" => "Pharmacy approved successfully"]);
    }

    private function rejectPharmacy($id) {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        $reason = isset($data['reason']) ? $data['reason'] : "Did not meet requirements.";

        $stmt = $this->conn->prepare("SELECT a.*, u.email, u.first_name FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = :id");
        $stmt->execute(['id' => $id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Pharmacy not found"]);
            return;
        }

        if ($agent['verification_status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Pharmacy is not in pending state"]);
            return;
        }

        $stmtUpdate = $this->conn->prepare("UPDATE agents SET verification_status = 'rejected' WHERE id = :id");
        $stmtUpdate->execute(['id' => $id]);

        $mailer = new Mailer();
        $pharmacyName = $agent['pharmacy_name'] ? $agent['pharmacy_name'] : $agent['first_name'];
        $mailer->sendPharmacyDeclined($agent['email'], $pharmacyName, $reason);

        echo json_encode(["success" => true, "message" => "Pharmacy rejected successfully"]);
    }

    private function getAllPharmacies() {
        $stmt = $this->conn->prepare("
            SELECT a.id as agent_table_id, a.agent_id, a.pharmacy_name, a.council_reg_no, a.fda_license_no, 
                   a.agent_type, a.region, a.bio, a.verification_status, a.created_at, 
                   u.id as user_id, u.first_name, u.last_name, u.email, u.phone
            FROM agents a
            JOIN users u ON a.user_id = u.id
            WHERE a.verification_status != 'pending'
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
        $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $pharmacies]);
    }

    private function togglePharmacyStatus($id) {
        $stmt = $this->conn->prepare("SELECT verification_status FROM agents WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Pharmacy not found"]);
            return;
        }

        $newStatus = $agent['verification_status'] === 'approved' ? 'rejected' : 'approved';

        $stmtUpdate = $this->conn->prepare("UPDATE agents SET verification_status = :status WHERE id = :id");
        $stmtUpdate->execute(['status' => $newStatus, 'id' => $id]);

        echo json_encode(["success" => true, "message" => "Pharmacy status updated to " . $newStatus, "newStatus" => $newStatus]);
    }

    private function getAllPatients() {
        $stmt = $this->conn->prepare("
            SELECT id, first_name, last_name, email, phone, created_at
            FROM users
            WHERE role = 'patient'
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $patients]);
    }

    private function updateProfile() {
        $input = json_decode(file_get_contents("php://input"), true);
        $userId = $this->user->id;

        $firstName = $input['first_name'] ?? '';
        $lastName = $input['last_name'] ?? '';
        $phone = $input['phone'] ?? '';
        $email = $input['email'] ?? '';
        
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($email)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "First name, last name, and email are required."]);
            return;
        }

        try {
            $this->conn->beginTransaction();

            if (!empty($newPassword)) {
                if (empty($currentPassword)) {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Current password is required to set a new password."]);
                    return;
                }

                $stmt = $this->conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $userDb = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!password_verify($currentPassword, $userDb['password'])) {
                    http_response_code(401);
                    echo json_encode(["success" => false, "message" => "Incorrect current password."]);
                    return;
                }

                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $this->conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, email = ?, password = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $phone, $email, $hashedPassword]);
            } else {
                $stmt = $this->conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, email = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $phone, $email, $userId]);
            }

            $this->conn->commit();

            echo json_encode([
                "success" => true, 
                "message" => "Profile updated successfully.",
                "user" => [
                    "id" => $userId,
                    "first_name" => $firstName,
                    "last_name" => $lastName,
                    "phone" => $phone,
                    "email" => $email,
                    "role" => "admin"
                ]
            ]);
        } catch(PDOException $e) {
            $this->conn->rollBack();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    }
}
?>
