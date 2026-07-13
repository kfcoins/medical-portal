<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';
require_once 'utils/Mailer.php';
require_once 'utils/Uuid.php';

class AuthController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && $action === 'register') {
            $this->register();
        } elseif ($method === 'POST' && $action === 'login') {
            $this->login();
        } elseif ($method === 'POST' && $action === 'verify-otp') {
            $this->verifyOtp();
        } elseif ($method === 'POST' && $action === 'resend-otp') {
            $this->resendOtp();
        } elseif ($method === 'GET' && $action === 'me') {
            $this->me();
        } elseif ($method === 'PUT' && $action === 'change-password') {
            $this->changePassword();
        } elseif ($method === 'POST' && $action === 'forgot-password') {
            $this->forgotPassword();
        } elseif ($method === 'POST' && $action === 'reset-password') {
            $this->resetPassword();
        } elseif ($method === 'POST' && $action === 'validate-step-1') {
            $this->validateStep1();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in auth"]);
        }
    }

    private function validateStep1() {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        
        $email = isset($data['email']) ? strtolower($data['email']) : null;
        $phone = isset($data['phone']) ? $data['phone'] : null;
        $ghanaCard = isset($data['ghanaCard']) ? $data['ghanaCard'] : null;

        $errors = [];

        if ($email) {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) $errors[] = "Email is already registered.";
        }

        if ($phone) {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE phone = :phone");
            $stmt->execute(['phone' => $phone]);
            if ($stmt->fetch()) $errors[] = "Phone number is already registered.";
        }

        if ($ghanaCard) {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE ghana_card = :card");
            $stmt->execute(['card' => $ghanaCard]);
            if ($stmt->fetch()) $errors[] = "Ghana Card is already registered.";
        }

        if (count($errors) > 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
        } else {
            echo json_encode(["success" => true, "message" => "Available"]);
        }
    }

    private function register() {
        $data = $_POST;
        if (!isset($data['firstName']) || !isset($data['lastName']) || !isset($data['email']) || !isset($data['phone']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing required fields"]);
            return;
        }

        $email = strtolower($data['email']);
        $phone = $data['phone'];

        // Check existing
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $stmt->execute(['email' => $email, 'phone' => $phone]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email or phone already registered"]);
            return;
        }

        $role = isset($data['role']) ? $data['role'] : 'patient';
        // Support roles like Patient, Pharmacy
        if (!in_array($role, ['patient', 'pharmacy', 'admin'])) {
            $role = 'patient';
        }

        $password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $this->conn->beginTransaction();
        try {
            $user_id = Uuid::v4();
            $stmt = $this->conn->prepare("INSERT INTO users (id, first_name, last_name, email, phone, password_hash, role, ghana_card, region, is_verified) VALUES (:id, :fn, :ln, :email, :phone, :pass, :role, :card, :region, 0)");
            $stmt->execute([
                'id' => $user_id,
                'fn' => $data['firstName'],
                'ln' => $data['lastName'],
                'email' => $email,
                'phone' => $phone,
                'pass' => $password_hash,
                'role' => $role,
                'card' => isset($data['ghanaCard']) ? $data['ghanaCard'] : null,
                'region' => isset($data['region']) ? $data['region'] : null
            ]);


            $mailer = new Mailer();

            // Create agent profile if role is pharmacy
            if ($role === 'pharmacy' || isset($data['agentType'])) {
                // Generate agent_id
                $stmtCount = $this->conn->query("SELECT COUNT(*) FROM agents");
                $count = $stmtCount->fetchColumn();
                $agent_id = "PA-GH-" . date('Y') . "-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                $agent_table_id = Uuid::v4();
                // Handle File Uploads
                $uploadDir = __DIR__ . '/../../uploads/documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $idFrontUrl = null;
                $pharmLicenseUrl = null;

                if (isset($_FILES['idFront']) && $_FILES['idFront']['error'] == 0) {
                    $ext = pathinfo($_FILES['idFront']['name'], PATHINFO_EXTENSION);
                    $fileName = 'id_' . $user_id . '.' . $ext;
                    if (move_uploaded_file($_FILES['idFront']['tmp_name'], $uploadDir . $fileName)) {
                        $idFrontUrl = '/mansro/uploads/documents/' . $fileName;
                    }
                }

                $idBackUrl = null;
                if (isset($_FILES['idBack']) && $_FILES['idBack']['error'] == 0) {
                    $ext = pathinfo($_FILES['idBack']['name'], PATHINFO_EXTENSION);
                    $fileName = 'id_back_' . $user_id . '.' . $ext;
                    if (move_uploaded_file($_FILES['idBack']['tmp_name'], $uploadDir . $fileName)) {
                        $idBackUrl = '/mansro/uploads/documents/' . $fileName;
                    }
                }

                if (isset($_FILES['pharmLicense']) && $_FILES['pharmLicense']['error'] == 0) {
                    $ext = pathinfo($_FILES['pharmLicense']['name'], PATHINFO_EXTENSION);
                    $fileName = 'license_' . $user_id . '.' . $ext;
                    if (move_uploaded_file($_FILES['pharmLicense']['tmp_name'], $uploadDir . $fileName)) {
                        $pharmLicenseUrl = '/mansro/uploads/documents/' . $fileName;
                    }
                }

                $stmtAgent = $this->conn->prepare("INSERT INTO agents (id, user_id, agent_id, pharmacy_name, council_reg_no, fda_license_no, agent_type, region, bio, verification_status, id_front_url, id_back_url, pharm_license_url) VALUES (:id, :uid, :aid, :pname, :creg, :freg, :type, :region, :bio, 'pending', :idFrontUrl, :idBackUrl, :pharmLicenseUrl)");
                $stmtAgent->execute([
                    'id' => $agent_table_id,
                    'uid' => $user_id,
                    'aid' => $agent_id,
                    'pname' => isset($data['bizName']) ? $data['bizName'] : null,
                    'creg' => isset($data['councilReg']) ? $data['councilReg'] : null,
                    'freg' => isset($data['fdaLicense']) ? $data['fdaLicense'] : null,
                    'type' => isset($data['agentType']) ? $data['agentType'] : $role,
                    'region' => isset($data['region']) ? $data['region'] : '',
                    'bio' => isset($data['bio']) ? $data['bio'] : null,
                    'idFrontUrl' => $idFrontUrl,
                    'idBackUrl' => $idBackUrl,
                    'pharmLicenseUrl' => $pharmLicenseUrl
                ]);

                $this->conn->commit();

                // Send pharmacy registration received email
                try {
                    $pharmacyName = isset($data['bizName']) ? $data['bizName'] : $data['firstName'];
                    $mailer->sendPharmacyReceived($email, $pharmacyName);

                    // Notify all admins
                    $stmtAdmins = $this->conn->query("SELECT email FROM users WHERE role = 'admin'");
                    $admins = $stmtAdmins->fetchAll();
                    foreach ($admins as $admin) {
                        $mailer->sendAdminNewPharmacyAlert($admin['email'], $pharmacyName, $email);
                    }
                } catch (Exception $e) {
                    error_log("Email sending failed during pharmacy registration: " . $e->getMessage());
                }

                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Registration successful! Your application is under review.",
                    "requires_verification" => false // Pharmacy waits for admin approval
                ]);
            } else {
                // Patient registration
                $otp = sprintf("%06d", mt_rand(1, 999999));
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $stmtOtp = $this->conn->prepare("UPDATE users SET email_otp = :otp, otp_expires_at = :exp WHERE id = :id");
                $stmtOtp->execute(['otp' => $otp, 'exp' => $expires_at, 'id' => $user_id]);

                $this->conn->commit();

                try {
                    $mailer->sendOTP($email, $otp);
                } catch (Exception $e) {
                    error_log("Email sending failed during patient registration: " . $e->getMessage());
                }

                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Registration successful! Please verify your email.",
                    "requires_verification" => true,
                    "email" => $email
                ]);
            }

        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Registration Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "A server error occurred during registration. Please try again later."]);
        }
    }

    private function login() {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        if (!isset($data['identifier']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Please provide credentials"]);
            return;
        }

        $identifier = $data['identifier'];
        $password = $data['password'];

        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :id1 OR phone = :id2");
        $stmt->execute(['id1' => strtolower($identifier), 'id2' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid credentials"]);
            return;
        }

        if (!$user['is_active']) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Account suspended. Contact support."]);
            return;
        }

        // Check if pharmacy is approved
        if ($user['role'] === 'pharmacy') {
            $stmtAgent = $this->conn->prepare("SELECT verification_status FROM agents WHERE user_id = :uid");
            $stmtAgent->execute(['uid' => $user['id']]);
            $agent = $stmtAgent->fetch();
            
            if ($agent && $agent['verification_status'] === 'pending') {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Your account is still pending administrative approval."]);
                return;
            } elseif ($agent && $agent['verification_status'] === 'rejected') {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Your application was rejected. Please contact support."]);
                return;
            }
        }

        // Generate and send 2FA OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmtOtp = $this->conn->prepare("UPDATE users SET email_otp = :otp, otp_expires_at = :exp WHERE id = :id");
        $stmtOtp->execute(['otp' => $otp, 'exp' => $expires_at, 'id' => $user['id']]);

        $mailer = new Mailer();
        $mailer->sendOTP($user['email'], $otp);

        echo json_encode([
            "success" => true,
            "message" => "OTP sent to your email.",
            "requires_verification" => true,
            "email" => $user['email']
        ]);
    }

    private function resendOtp() {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        if (!isset($data['email'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email is required"]);
            return;
        }

        $email = strtolower($data['email']);
        $stmt = $this->conn->prepare("SELECT id, email FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found"]);
            return;
        }

        // Generate and send 2FA OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmtOtp = $this->conn->prepare("UPDATE users SET email_otp = :otp, otp_expires_at = :exp WHERE id = :id");
        $stmtOtp->execute(['otp' => $otp, 'exp' => $expires_at, 'id' => $user['id']]);

        $mailer = new Mailer();
        $mailer->sendOTP($user['email'], $otp);

        echo json_encode([
            "success" => true,
            "message" => "A new OTP has been sent to your email."
        ]);
    }

    private function verifyOtp() {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        if (!isset($data['email']) || !isset($data['otp'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email and OTP are required"]);
            return;
        }

        $email = strtolower($data['email']);
        $otp = $data['otp'];

        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found"]);
            return;
        }

        // Check OTP and expiration
        if ($user['email_otp'] !== $otp) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid OTP"]);
            return;
        }

        if (strtotime($user['otp_expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "OTP has expired"]);
            return;
        }

        // Verification successful
        $stmtUpdate = $this->conn->prepare("UPDATE users SET email_otp = NULL, otp_expires_at = NULL, is_verified = 1 WHERE id = :id");
        $stmtUpdate->execute(['id' => $user['id']]);

        $token = Jwt::encode(['id' => $user['id'], 'role' => $user['role'], 'exp' => time() + (7 * 24 * 60 * 60)]);
        
        unset($user['password_hash']);
        unset($user['email_otp']);
        unset($user['otp_expires_at']);

        echo json_encode([
            "success" => true,
            "message" => "Verification successful! Welcome, " . $user['first_name'],
            "token" => $token,
            "user" => $user,
            "role" => $user['role']
        ]);
    }

    private function me() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $stmt = $this->conn->prepare("SELECT id, first_name, last_name, email, phone, role, ghana_card, region, is_verified, is_active FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch();

        $agentProfile = null;
        if ($user['role'] === 'pharmacy') {
            $stmtAgent = $this->conn->prepare("SELECT * FROM agents WHERE user_id = :uid");
            $stmtAgent->execute(['uid' => $user_id]);
            $agentProfile = $stmtAgent->fetch();
        }

        echo json_encode(["success" => true, "user" => $user, "agentProfile" => $agentProfile]);
    }

    private function changePassword() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        
        $data = $_POST;
        parse_str(file_get_contents("php://input"), $put_vars);
        if(empty($data)) $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Passwords required"]);
            return;
        }

        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute(['id' => $user_id]);
        $user = $stmt->fetch();

        if (!password_verify($data['currentPassword'], $user['password_hash'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Current password is incorrect"]);
            return;
        }

        $new_hash = password_hash($data['newPassword'], PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->conn->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $stmt->execute(['hash' => $new_hash, 'id' => $user_id]);

        echo json_encode(["success" => true, "message" => "Password updated successfully"]);
    }
    private function forgotPassword() {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        if (!isset($data['email'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email is required"]);
            return;
        }

        $email = strtolower($data['email']);
        $stmt = $this->conn->prepare("SELECT id, email, first_name FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Always return success even if user not found to prevent email enumeration
        if (!$user) {
            echo json_encode(["success" => true, "message" => "If the email exists, an OTP has been sent."]);
            return;
        }

        $otp = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $stmtOtp = $this->conn->prepare("UPDATE users SET email_otp = :otp, otp_expires_at = :exp WHERE id = :id");
        $stmtOtp->execute(['otp' => $otp, 'exp' => $expires_at, 'id' => $user['id']]);

        $mailer = new Mailer();
        $mailer->sendPasswordResetOTP($user['email'], $otp);

        echo json_encode(["success" => true, "message" => "If the email exists, an OTP has been sent."]);
    }

    private function resetPassword() {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
        if (!isset($data['email']) || !isset($data['otp']) || !isset($data['new_password'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Email, OTP, and new password are required"]);
            return;
        }

        $email = strtolower($data['email']);
        $otp = $data['otp'];
        $new_password = $data['new_password'];

        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "User not found"]);
            return;
        }

        if ($user['email_otp'] !== $otp) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Invalid OTP"]);
            return;
        }

        if (strtotime($user['otp_expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "OTP has expired"]);
            return;
        }

        $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmtUpdate = $this->conn->prepare("UPDATE users SET password_hash = :hash, email_otp = NULL, otp_expires_at = NULL, is_verified = 1 WHERE id = :id");
        $stmtUpdate->execute(['hash' => $new_hash, 'id' => $user['id']]);

        $token = Jwt::encode(['id' => $user['id'], 'role' => $user['role'], 'exp' => time() + (7 * 24 * 60 * 60)]);
        
        unset($user['password_hash']);
        unset($user['email_otp']);
        unset($user['otp_expires_at']);

        echo json_encode([
            "success" => true, 
            "message" => "Password reset successfully",
            "token" => $token,
            "user" => $user,
            "role" => $user['role']
        ]);
    }
}
?>
