<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';

class MessageController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET' && $action === 'conversations') {
            $this->getConversations();
        } elseif ($method === 'GET' && $action === 'history' && $id) {
            $this->getMessageHistory($id);
        } elseif ($method === 'POST' && $action === 'send') {
            $this->sendMessage();
        } elseif ($method === 'POST' && $action === 'upload') {
            $this->uploadAttachment();
        } elseif ($method === 'PATCH' && $action === 'read' && $id) {
            $this->markAsRead($id);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in messages"]);
        }
    }

    private function getConversations() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        // Auto-delete messages older than 7 days
        $cleanupStmt = $this->conn->prepare("DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $cleanupStmt->execute();

        // Get the latest message for each conversation
        // Group by the other user
        $query = "
            SELECT 
                u.id as other_user_id, 
                u.first_name, 
                u.last_name, 
                u.role,
                a.pharmacy_name,
                m.message, 
                m.created_at, 
                m.is_read,
                m.sender_id
            FROM users u
            LEFT JOIN agents a ON u.id = a.user_id
            JOIN (
                SELECT 
                    CASE 
                        WHEN sender_id = :uid THEN receiver_id 
                        ELSE sender_id 
                    END as contact_id,
                    MAX(created_at) as last_msg_time
                FROM messages
                WHERE sender_id = :uid OR receiver_id = :uid
                GROUP BY contact_id
            ) latest ON u.id = latest.contact_id
            JOIN messages m ON 
                (m.sender_id = :uid AND m.receiver_id = u.id AND m.created_at = latest.last_msg_time) OR 
                (m.receiver_id = :uid AND m.sender_id = u.id AND m.created_at = latest.last_msg_time)
            ORDER BY m.created_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute(['uid' => $user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deduplicate conversations in PHP (fixes issues where multiple messages have the exact same max created_at)
        $unique = [];
        foreach($conversations as $c) {
            $cid = $c['other_user_id'];
            if(!isset($unique[$cid])) {
                $unique[$cid] = $c;
            }
        }
        $conversations = array_values($unique);

        // Map data safely
        $formatted = array_map(function($c) use ($user_id) {
            $name = $c['role'] === 'pharmacy' && !empty($c['pharmacy_name']) ? $c['pharmacy_name'] : $c['first_name'] . ' ' . $c['last_name'];
            return [
                'contact_id' => $c['other_user_id'],
                'contact_name' => $name,
                'contact_role' => $c['role'],
                'last_message' => $c['message'],
                'created_at' => $c['created_at'],
                'unread_count' => ($c['sender_id'] !== $user_id && !$c['is_read']) ? 1 : 0 // Simplified unread
            ];
        }, $conversations);

        echo json_encode(["success" => true, "conversations" => $formatted]);
    }

    private function getMessageHistory($contact_id) {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        // Auto-delete messages older than 7 days
        $cleanupStmt = $this->conn->prepare("DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $cleanupStmt->execute();

        $query = "
            SELECT m.*, 
                   s.first_name as sender_first, s.last_name as sender_last, s.role as sender_role,
                   r.first_name as receiver_first, r.last_name as receiver_last, r.role as receiver_role
            FROM messages m
            JOIN users s ON m.sender_id = s.id
            JOIN users r ON m.receiver_id = r.id
            WHERE (m.sender_id = :uid AND m.receiver_id = :cid) 
               OR (m.sender_id = :cid AND m.receiver_id = :uid)
            ORDER BY m.created_at ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute(['uid' => $user_id, 'cid' => $contact_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark as read if the current user is the receiver
        $updateStmt = $this->conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = :uid AND sender_id = :cid AND is_read = 0");
        $updateStmt->execute(['uid' => $user_id, 'cid' => $contact_id]);

        echo json_encode(["success" => true, "messages" => $messages]);
    }

    private function sendMessage() {
        $decoded = Jwt::authenticate();
        $sender_id = $decoded['id'];

        $input = json_decode(file_get_contents("php://input"), true);
        $receiver_id = isset($input['receiver_id']) ? $input['receiver_id'] : null;
        $message = isset($input['message']) ? trim($input['message']) : null;

        if (!$receiver_id || !$message) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Receiver ID and message are required"]);
            return;
        }

        try {
            $msg_id = "MSG-" . time() . "-" . bin2hex(random_bytes(4));
            $stmt = $this->conn->prepare("INSERT INTO messages (id, sender_id, receiver_id, message) VALUES (:id, :sid, :rid, :msg)");
            $stmt->execute([
                'id' => $msg_id,
                'sid' => $sender_id,
                'rid' => $receiver_id,
                'msg' => $message
            ]);

            // Fetch the newly created message
            $stmtFetch = $this->conn->prepare("SELECT * FROM messages WHERE id = :id");
            $stmtFetch->execute(['id' => $msg_id]);
            $newMsg = $stmtFetch->fetch(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "message" => "Message sent successfully", "data" => $newMsg]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error sending message: " . $e->getMessage()]);
        }
    }

    
    private function uploadAttachment() {
        $decoded = Jwt::authenticate();
        $sender_id = $decoded['id'];

        $receiver_id = isset($_POST['receiver_id']) ? $_POST['receiver_id'] : null;

        if (!$receiver_id) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Receiver ID is required"]);
            return;
        }

        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Please upload a valid file."]);
            return;
        }

        $uploadDir = __DIR__ . '/../../uploads/chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Only JPG, PNG and PDF files are allowed."]);
            return;
        }

        $newFileName = "chat_" . time() . "_" . uniqid() . "." . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $fileUrl = 'uploads/chat/' . $newFileName;
            
            // Generate a JSON payload for the message
            $payload = [
                'type' => 'attachment',
                'url' => $fileUrl,
                'fileType' => ($fileExtension === 'pdf') ? 'pdf' : 'image',
                'originalName' => $fileName
            ];
            
            $messageContent = json_encode($payload);

            http_response_code(201);
            echo json_encode([
                "success" => true, 
                "message" => "Attachment uploaded successfully", 
                "url" => $fileUrl,
                "fileType" => ($fileExtension === 'pdf') ? 'pdf' : 'image',
                "originalName" => $fileName
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Failed to move uploaded file."]);
        }
    }

    private function markAsRead($contact_id) {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];

        $stmt = $this->conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = :uid AND sender_id = :cid");
        $stmt->execute(['uid' => $user_id, 'cid' => $contact_id]);

        echo json_encode(["success" => true, "message" => "Messages marked as read"]);
    }
}
?>
