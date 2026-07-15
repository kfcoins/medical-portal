<?php
namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use PDO;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/config/Jwt.php';

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $usersMap; // Connection obj -> user_id
    protected $reverseMap; // user_id -> Connection obj
    protected $conn;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->usersMap = new \SplObjectStorage;
        $this->reverseMap = [];
        
        $db = new \Database();
        $this->conn = $db->getConnection();
        
        echo "Chat server started...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $querystring = $conn->httpRequest->getUri()->getQuery();
        parse_str($querystring, $query);
        
        if (isset($query['token'])) {
            $decoded = \Jwt::decode($query['token']);
            if ($decoded && isset($decoded['id'])) {
                $userId = $decoded['id'];
                $this->clients->attach($conn);
                $this->usersMap->attach($conn, $userId);
                $this->reverseMap[$userId] = $conn;
                echo "New connection! ({$conn->resourceId}) - User: $userId\n";
            } else {
                $conn->close();
            }
        } else {
            $conn->close();
        }
    }

    private function checkConnection() {
        try {
            $this->conn->query("SELECT 1");
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'server has gone away') !== false || strpos($e->getMessage(), 'Lost connection') !== false) {
                $db = new \Database();
                $this->conn = $db->getConnection();
            } else {
                throw $e;
            }
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        if (!isset($this->usersMap[$from])) return;
        $userId = $this->usersMap[$from];
        $data = json_decode($msg, true);
        
        $this->checkConnection();
        
        if (isset($data['type']) && $data['type'] === 'typing') {
            $receiverId = $data['receiver_id'];
            if (isset($this->reverseMap[$receiverId])) {
                $recipientConn = $this->reverseMap[$receiverId];
                $recipientConn->send(json_encode([
                    'type' => 'typing',
                    'sender_id' => $userId
                ]));
            }
            return;
        }

        $receiverId = isset($data['receiver_id']) ? $data['receiver_id'] : null;
        $message = isset($data['message']) ? trim($data['message']) : null;
        
        if ($receiverId && $message) {
            // Save to DB
            $msg_id = "MSG-" . time() . "-" . bin2hex(random_bytes(4));
            $stmt = $this->conn->prepare("INSERT INTO messages (id, sender_id, receiver_id, message) VALUES (:id, :sid, :rid, :msg)");
            $stmt->execute([
                'id' => $msg_id,
                'sid' => $userId,
                'rid' => $receiverId,
                'msg' => $message
            ]);
            
            // Fetch newly created message
            $stmtFetch = $this->conn->prepare("SELECT m.*, s.first_name as sender_first, s.last_name as sender_last, s.role as sender_role FROM messages m JOIN users s ON m.sender_id = s.id WHERE m.id = :id");
            $stmtFetch->execute(['id' => $msg_id]);
            $newMsg = $stmtFetch->fetch(PDO::FETCH_ASSOC);
            
            $payload = json_encode([
                'type' => 'message',
                'data' => $newMsg
            ]);
            
            // Send back to sender so their UI updates with actual DB object
            $from->send($payload);

            // Send to receiver if online
            if (isset($this->reverseMap[$receiverId])) {
                $recipientConn = $this->reverseMap[$receiverId];
                $recipientConn->send($payload);
            } else {
                // User is offline, send email notification
                $this->sendOfflineEmail($receiverId, $userId, $message);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if ($this->usersMap->contains($conn)) {
            $userId = $this->usersMap[$conn];
            unset($this->reverseMap[$userId]);
            $this->usersMap->detach($conn);
        }
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    private function sendOfflineEmail($receiverId, $senderId, $message) {
        $this->checkConnection();
        $stmt = $this->conn->prepare("SELECT email, first_name FROM users WHERE id = :id");
        $stmt->execute(['id' => $receiverId]);
        $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt2 = $this->conn->prepare("SELECT first_name, role FROM users WHERE id = :id");
        $stmt2->execute(['id' => $senderId]);
        $sender = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($receiver && $sender) {
            $mail = new PHPMailer(true);
            try {
                // Setup simple mail transport for local
                $mail->isMail(); 
                $mail->setFrom('noreply@pharmatrust.gh', 'PharmaTrust GH');
                $mail->addAddress($receiver['email'], $receiver['first_name']);
                $mail->Subject = 'New Message from ' . $sender['first_name'];
                $mail->Body    = "Hello " . $receiver['first_name'] . ",\n\nYou received a new message from " . $sender['first_name'] . " on PharmaTrust GH:\n\n\"" . $message . "\"\n\nLog in to reply.\nNote: Messages are automatically deleted after 7 days.";
                $mail->send();
                echo "Sent offline email notification to {$receiver['email']}\n";
            } catch (Exception $e) {
                echo "Mailer Error: " . $mail->ErrorInfo . "\n";
            }
        }
    }
}
