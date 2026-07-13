<?php
require_once 'config/Database.php';

class ContactController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && $action === '') {
            $this->createMessage();
        } elseif ($method === 'GET' && $action === '') {
            $this->listMessages();
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in contact"]);
        }
    }

    private function createMessage() {
        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        if (!isset($input['name']) || !isset($input['phone']) || !isset($input['message'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Name, phone, and message are required."]);
            return;
        }

        $stmt = $this->conn->prepare("INSERT INTO contacts (name, phone, email, user_type, message) VALUES (:name, :phone, :email, :utype, :msg)");
        $stmt->execute([
            'name' => $input['name'],
            'phone' => $input['phone'],
            'email' => isset($input['email']) ? $input['email'] : null,
            'utype' => isset($input['userType']) ? $input['userType'] : null,
            'msg' => $input['message']
        ]);

        $id = $this->conn->lastInsertId();
        
        $stmt = $this->conn->prepare("SELECT * FROM contacts WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $contact = $stmt->fetch();

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Thank you! Your message has been received. Our team will contact you within 24 hours.",
            "contact" => $contact
        ]);
    }

    private function listMessages() {
        $stmt = $this->conn->query("SELECT * FROM contacts ORDER BY created_at DESC");
        $messages = $stmt->fetchAll();

        echo json_encode([
            "success" => true,
            "messages" => $messages,
            "total" => count($messages)
        ]);
    }
}
?>
