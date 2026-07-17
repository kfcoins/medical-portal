<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Env.php';

class WebhookController {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function handlePaystackWebhook() {
        // Only a POST request is allowed
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit();
        }

        // Retrieve the request's body
        $input = file_get_contents('php://input');
        $signature = (isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '');
        $secretKey = Env::get('PAYSTACK_SECRET_KEY', '');

        // Validate event
        if (!$signature || ($signature !== hash_hmac('sha512', $input, $secretKey))) {
            // Silently abort to avoid leaking information to hackers
            http_response_code(401);
            exit();
        }

        http_response_code(200);

        // Parse event
        $event = json_decode($input);

        // Do something with $event
        if ($event->event === 'charge.success') {
            $reference = $event->data->reference;
            $amount = $event->data->amount / 100; // Paystack sends in pesewas
            
            // Find the order by reference and update its status
            $stmt = $this->conn->prepare("SELECT id, status FROM orders WHERE paystack_reference = :reference AND payment_status != 'paid'");
            $stmt->execute(['reference' => $reference]);
            $order = $stmt->fetch();

            if ($order) {
                // If it's a new paid order, it might have been pending, so we update status to confirmed
                $newStatus = ($order['status'] === 'pending') ? 'confirmed' : $order['status'];

                $updateStmt = $this->conn->prepare("UPDATE orders SET payment_status = 'paid', status = :status WHERE id = :id");
                $updateStmt->execute([
                    'status' => $newStatus,
                    'id' => $order['id']
                ]);
            }
        }
        
        exit();
    }
}
?>
