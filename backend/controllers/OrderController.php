<?php
require_once 'config/Database.php';
require_once 'config/Jwt.php';
require_once __DIR__ . '/../utils/Mailer.php';

class OrderController {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function handleRequest($action, $id = null) {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && $action === '') {
            $this->createOrder();
        } elseif ($method === 'GET' && $action === 'my') {
            $this->myOrders();
        } elseif ($method === 'GET' && $action !== '' && $action !== 'my' && $action !== 'patient-stats') {
            $this->getOrder($action);
        } elseif ($method === 'POST' && $action === 'checkout') {
            $this->checkout();
        } elseif ($method === 'GET' && $action === 'patient-stats') {
            $this->patientStats();
        } elseif ($method === 'POST' && $action === 'review-nhis') {
            $this->reviewNhis();
        } elseif ($method === 'PATCH' && $action === 'batch-status') {
            $this->batchUpdateStatus();
        } elseif ($method === 'PATCH' && $id === 'status' && $action !== '') { 
            $this->updateStatus($action);
        } elseif ($method === 'PATCH' && $id === 'payment_status' && $action !== '') { 
            $this->updatePaymentStatus($action);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Endpoint not found in orders"]);
        }
    }

    private function sendAsyncResponseAndContinue($response) {
        ignore_user_abort(true);
        set_time_limit(0);

        if (function_exists('fastcgi_finish_request')) {
            echo json_encode($response);
            if (session_id()) session_write_close();
            fastcgi_finish_request();
            return;
        }

        ob_start();
        echo json_encode($response);
        $size = ob_get_length();
        header("Connection: close");
        header("Content-Encoding: none");
        header("Content-Length: " . $size);
        ob_end_flush();
        @ob_flush();
        flush();
        if (session_id()) session_write_close();
    }

    private function createOrder() {
        $decoded = Jwt::authenticate();
        $patient_id = $decoded['id'];

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $agentId = isset($input['agentId']) ? $input['agentId'] : (isset($input['agent']) ? $input['agent'] : null);
        $items = isset($input['items']) ? $input['items'] : [];
        
        if (empty($items)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Order must have at least one item"]);
            return;
        }

        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['unitPrice'] * $item['quantity'];
        }

        $this->conn->beginTransaction();
        try {
            $stmtCount = $this->conn->query("SELECT COUNT(*) FROM orders");
            $count = $stmtCount->fetchColumn();
            $orderNo = "ORD-" . time() . "-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            $paymentMethod = isset($input['paymentMethod']) ? $input['paymentMethod'] : 'cash';
            $paymentReference = isset($input['paymentReference']) ? $input['paymentReference'] : null;
            $deliveryAddress = isset($input['deliveryAddress']) ? $input['deliveryAddress'] : null;
            $prescription = isset($input['prescription']) ? $input['prescription'] : null;
            $notes = isset($input['notes']) ? $input['notes'] : null;
            $nhisDeduction = isset($input['nhisDeduction']) ? $input['nhisDeduction'] : 0;
            $amountDue = $totalAmount - $nhisDeduction;

            $stmt = $this->conn->prepare("INSERT INTO orders (order_no, paystack_reference, patient_id, agent_id, total_amount, nhis_deduction, amount_due, prescription, payment_method, delivery_address, notes) VALUES (:on, :pref, :pid, :aid, :total, :nhis, :due, :presc, :pm, :da, :notes)");
            $stmt->execute([
                'on' => $orderNo,
                'pref' => $paymentReference,
                'pid' => $patient_id,
                'aid' => $agentId,
                'total' => $totalAmount,
                'nhis' => $nhisDeduction,
                'due' => $amountDue,
                'presc' => $prescription,
                'pm' => $paymentMethod,
                'da' => $deliveryAddress,
                'notes' => $notes
            ]);
            
            $order_id = $this->conn->lastInsertId();

            // Insert items
            foreach ($items as $item) {
                // Determine medicine_id format. If Mongo, it's string. Since we changed to MySQL, it'll be an int.
                $med_id = is_array($item['medicine']) ? (isset($item['medicine']['id']) ? $item['medicine']['id'] : $item['medicine']['_id']) : (isset($item['medicine_id']) ? $item['medicine_id'] : $item['medicine']);
                
                $stmtItem = $this->conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price) VALUES (:oid, :mid, :qty, :up)");
                $stmtItem->execute([
                    'oid' => $order_id,
                    'mid' => $med_id,
                    'qty' => $item['quantity'],
                    'up' => $item['unitPrice']
                ]);
            }

            $this->conn->commit();

            // Fetch created order to return
            $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = :id");
            $stmt->execute(['id' => $order_id]);
            $order = $stmt->fetch();
            $order['items'] = $items;

            // Fetch details for emails
            $mailer = new Mailer();
            $stmtP = $this->conn->prepare("SELECT first_name, email FROM users WHERE id = :id");
            $stmtP->execute(['id' => $patient_id]);
            $patient = $stmtP->fetch();

            $stmtA = $this->conn->prepare("SELECT a.pharmacy_name, u.email FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = :aid");
            $stmtA->execute(['aid' => $agentId]);
            $pharmacy = $stmtA->fetch();

            if ($patient) {
                $mailer->sendOrderPlacedPatient($patient['email'], $patient['first_name'], $orderNo, $totalAmount);
            }
            if ($pharmacy) {
                $mailer->sendOrderPlacedPharmacy($pharmacy['email'], $pharmacy['pharmacy_name'], $orderNo, $totalAmount);
            }

            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Order placed successfully", "order" => $order]);
            exit;
        } catch (Exception $e) {
            $this->conn->rollBack();
            http_response_code(400);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    private function myOrders() {
        $decoded = Jwt::authenticate();
        $user_id = $decoded['id'];
        $role = $decoded['role'];

        $filterSql = "";
        $params = [];

        if ($role === 'pharmacy') {
            $stmt = $this->conn->prepare("SELECT id FROM agents WHERE user_id = :uid");
            $stmt->execute(['uid' => $user_id]);
            $agent = $stmt->fetch();
            if ($agent) {
                $filterSql = "WHERE o.agent_id = :aid";
                $params['aid'] = $agent['id'];
            } else {
                // If they are agent role but no profile yet
                $filterSql = "WHERE o.agent_id = -1";
            }
        } else {
            $filterSql = "WHERE o.patient_id = :pid";
            $params['pid'] = $user_id;
        }

        $query = "SELECT o.*, a.pharmacy_name, a.region as agent_region, CONCAT(u.first_name, ' ', u.last_name) as patient_name, u.nhis_number as patient_nhis_number, u.nhis_card_front_url as patient_nhis_front_url, u.nhis_card_back_url as patient_nhis_back_url, u.nhis_status as patient_nhis_status
                  FROM orders o 
                  LEFT JOIN agents a ON o.agent_id = a.id 
                  LEFT JOIN users u ON o.patient_id = u.id
                  $filterSql 
                  ORDER BY o.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $order['total_amount'] = (float)$order['total_amount'];
            $order['amount_due'] = (float)$order['amount_due'];

            // fetch items
            $stmtItem = $this->conn->prepare("SELECT oi.*, m.name, m.price, m.image_url FROM order_items oi JOIN medicines m ON oi.medicine_id = m.id WHERE oi.order_id = :oid");
            $stmtItem->execute(['oid' => $order['id']]);
            $items = $stmtItem->fetchAll();
            $order['items'] = [];
            foreach ($items as $item) {
                $order['items'][] = [
                    'quantity' => (int)$item['quantity'],
                    'unitPrice' => (float)$item['unit_price'],
                    'medicine' => [
                        'id' => $item['medicine_id'],
                        'name' => $item['name'],
                        'price' => (float)$item['price'],
                        'imageUrl' => $item['image_url']
                    ]
                ];
            }
            $order['agent'] = [
                'id' => $order['agent_id'],
                'pharmacyName' => $order['pharmacy_name'],
                'region' => $order['agent_region']
            ];
            
            // Send patient NHIS details only if pharmacy role and payment method involves NHIS
            if ($role === 'pharmacy' && $order['payment_method'] === 'nhis') {
                $order['patient_nhis'] = [
                    'number' => $order['patient_nhis_number'],
                    'front_url' => $order['patient_nhis_front_url'],
                    'back_url' => $order['patient_nhis_back_url'],
                    'status' => $order['patient_nhis_status']
                ];
            }
        }

        echo json_encode(["success" => true, "orders" => $orders]);
    }

    private function getOrder($id) {
        $decoded = Jwt::authenticate();

        $query = "SELECT o.*, u.first_name as p_fn, u.last_name as p_ln, u.phone as p_phone, 
                         au.first_name as a_fn, au.last_name as a_ln, au.phone as a_phone
                  FROM orders o 
                  JOIN users u ON o.patient_id = u.id 
                  JOIN agents a ON o.agent_id = a.id
                  JOIN users au ON a.user_id = au.id
                  WHERE o.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Order not found"]);
            return;
        }

        $order['patient'] = [
            'firstName' => $order['p_fn'],
            'lastName' => $order['p_ln'],
            'phone' => $order['p_phone']
        ];
        $order['agent'] = [
            'user' => [
                'firstName' => $order['a_fn'],
                'lastName' => $order['a_ln'],
                'phone' => $order['a_phone']
            ]
        ];

        // items
        $stmtItem = $this->conn->prepare("SELECT oi.*, m.* FROM order_items oi JOIN medicines m ON oi.medicine_id = m.id WHERE oi.order_id = :oid");
        $stmtItem->execute(['oid' => $order['id']]);
        $items = $stmtItem->fetchAll();
        $order['items'] = [];
        foreach ($items as $item) {
            $medicine = $item; // extract medicine fields
            $order['items'][] = [
                'quantity' => (int)$item['quantity'],
                'unitPrice' => (float)$item['unit_price'],
                'medicine' => $medicine
            ];
        }

        echo json_encode(["success" => true, "order" => $order]);
    }

    private function updateStatus($id) {
        $decoded = Jwt::authenticate();
        // Agent or Admin only
        
        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $status = isset($input['status']) ? $input['status'] : null;
        $paymentStatus = isset($input['paymentStatus']) ? $input['paymentStatus'] : null;

        $allowed = ['pending','confirmed','dispensed','delivered','cancelled'];
        if ($status && !in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid status"]);
            return;
        }

        $updates = [];
        $params = ['id' => $id];

        if ($status) {
            $updates[] = "status = :status";
            $params['status'] = $status;
        }
        if ($paymentStatus) {
            $updates[] = "payment_status = :ps";
            $params['ps'] = $paymentStatus;
        }

        if (count($updates) === 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Nothing to update"]);
            return;
        }

        $updateSql = implode(", ", $updates);
        $stmt = $this->conn->prepare("UPDATE orders SET $updateSql WHERE id = :id");
        $stmt->execute($params);

        // Fetch
        $stmt = $this->conn->prepare("SELECT o.*, u.first_name, u.email FROM orders o JOIN users u ON o.patient_id = u.id WHERE o.id = :id");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        $this->sendAsyncResponseAndContinue(["success" => true, "order" => $order, "message" => "Status updated successfully"]);

        if ($status) {
            error_log("Sending status update email for order " . $order['order_no'] . " to " . $order['email']);
            $mailer = new Mailer();
            $res = $mailer->sendOrderStatusChanged($order['email'], $order['first_name'], $order['order_no'], $status);
            error_log("Email send result: " . ($res ? 'true' : 'false'));
        }
    }

    private function updatePaymentStatus($id) {
        $decoded = Jwt::authenticate();
        if (!in_array($decoded['role'], ['pharmacy', 'admin'])) {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $data = json_decode(file_get_contents("php://input"), true);
        $status = isset($data['status']) ? $data['status'] : null;

        if (!$status || !in_array($status, ['paid', 'unpaid'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid payment status"]);
            return;
        }

        $stmtCheck = $this->conn->prepare("SELECT payment_method FROM orders WHERE id = :id");
        $stmtCheck->execute(['id' => $id]);
        $existingOrder = $stmtCheck->fetch();

        if ($existingOrder && in_array($existingOrder['payment_method'], ['card', 'momo'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Online payments cannot have their status changed manually"]);
            return;
        }

        $stmt = $this->conn->prepare("UPDATE orders SET payment_status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);

        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $order = $stmt->fetch();

        echo json_encode(["success" => true, "order" => $order, "message" => "Payment status updated successfully"]);
    }

    private function batchUpdateStatus() {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'pharmacy' && $decoded['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Unauthorized"]);
            return;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $orderIds = isset($input['orderIds']) ? $input['orderIds'] : [];
        $status = isset($input['status']) ? $input['status'] : null;

        if (empty($orderIds) || !is_array($orderIds)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "No orders selected"]);
            return;
        }

        $allowed = ['pending','confirmed','dispensed','delivered','cancelled'];
        if ($status && !in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Invalid status"]);
            return;
        }

        if (!$status) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "No status provided"]);
            return;
        }

        $inQuery = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "UPDATE orders SET status = ? WHERE id IN ($inQuery)";
        
        $params = array_merge([$status], $orderIds);
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $this->sendAsyncResponseAndContinue(["success" => true, "message" => "Orders updated successfully"]);

        // Send emails
        $mailer = new Mailer();
        $stmtF = $this->conn->prepare("SELECT o.order_no, u.first_name, u.email FROM orders o JOIN users u ON o.patient_id = u.id WHERE o.id IN ($inQuery)");
        $stmtF->execute($orderIds);
        $updatedOrders = $stmtF->fetchAll();

        foreach ($updatedOrders as $uo) {
            error_log("Batch: Sending status update email for order " . $uo['order_no'] . " to " . $uo['email']);
            $res = $mailer->sendOrderStatusChanged($uo['email'], $uo['first_name'], $uo['order_no'], $status);
            error_log("Batch Email send result: " . ($res ? 'true' : 'false'));
        }
    }

    private function patientStats() {
        $decoded = Jwt::authenticate();
        $patient_id = $decoded['id'];
        
        if ($decoded['role'] !== 'patient') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Access denied. Patient only."]);
            return;
        }

        // 1. Active Prescriptions: Orders that are not delivered or cancelled (assuming pending, confirmed, dispensed)
        $stmt1 = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE patient_id = :pid AND status IN ('pending', 'confirmed', 'dispensed')");
        $stmt1->execute(['pid' => $patient_id]);
        $active_prescriptions = $stmt1->fetchColumn();

        // 2. Verified Meds: Count of items in this patient's orders where the medicine is FDA approved
        $stmt2 = $this->conn->prepare("SELECT COUNT(oi.id) FROM order_items oi 
                                       JOIN orders o ON oi.order_id = o.id 
                                       JOIN medicines m ON oi.medicine_id = m.id 
                                       WHERE o.patient_id = :pid AND m.fda_approved = 1");
        $stmt2->execute(['pid' => $patient_id]);
        $verified_meds = $stmt2->fetchColumn();

        // 3. Pending Deliveries: Orders that are 'dispensed' but not yet delivered
        $stmt3 = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE patient_id = :pid AND status = 'dispensed'");
        $stmt3->execute(['pid' => $patient_id]);
        $pending_deliveries = $stmt3->fetchColumn();

        echo json_encode([
            "success" => true,
            "stats" => [
                "active_prescriptions" => (int)$active_prescriptions,
                "verified_meds" => (int)$verified_meds,
                "pending_deliveries" => (int)$pending_deliveries
            ]
        ]);
    }

    private function checkout() {
        $decoded = Jwt::authenticate();
        $patient_id = $decoded['id'];

        $input = json_decode(file_get_contents("php://input"), true);
        if (empty($input)) $input = $_POST;

        $items = isset($input['items']) ? $input['items'] : [];
        $paymentMethod = isset($input['paymentMethod']) ? $input['paymentMethod'] : 'cash';
        $deliveryAddress = isset($input['deliveryAddress']) ? $input['deliveryAddress'] : null;
        $reference = isset($input['reference']) ? $input['reference'] : null;

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Cart is empty"]);
            return;
        }

        // Verify Paystack payment if applicable
        if (in_array($paymentMethod, ['card', 'momo']) && $reference) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer " . Env::get('PAYSTACK_SECRET_KEY', ''),
                    "Cache-Control: no-cache",
                ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            
            if ($err) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Payment verification failed", "error" => $err]);
                return;
            }

            $tranx = json_decode($response);
            if (!$tranx->status || $tranx->data->status !== 'success') {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Payment verification failed", "error" => "Transaction not successful on Paystack"]);
                return;
            }

            // Trust Paystack's verified channel over the frontend's input
            if (isset($tranx->data->channel)) {
                $channel = $tranx->data->channel;
                if ($channel === 'card') {
                    $paymentMethod = 'card';
                } elseif ($channel === 'mobile_money') {
                    $paymentMethod = 'momo';
                } elseif ($channel === 'bank') {
                    $paymentMethod = 'bank';
                } else {
                    $paymentMethod = $channel; // fallback for QR, USSD, etc.
                }
            }
        }

        $this->conn->beginTransaction();
        try {
            // Group items by agent_id
            $ordersByAgent = [];
            foreach ($items as $item) {
                $med_id = $item['medicine_id'] ?? (isset($item['medicine']) && is_array($item['medicine']) ? $item['medicine']['id'] : ($item['medicine'] ?? null));
                
                // Fetch medicine details
                $stmtMed = $this->conn->prepare("SELECT m.agent_id, m.price, a.allow_pay_on_delivery FROM medicines m JOIN agents a ON m.agent_id = a.id WHERE m.id = :id");
                $stmtMed->execute(['id' => $med_id]);
                $med = $stmtMed->fetch();

                if (!$med) throw new Exception("Medicine not found: " . $med_id);

                $agent_id = $med['agent_id'];
                if (!$agent_id) throw new Exception("Medicine is not linked to any pharmacy: " . $med_id);

                if ($paymentMethod === 'cash' && $med['allow_pay_on_delivery'] == 0) {
                    throw new Exception("One or more items in your cart do not support Pay on Delivery.");
                }

                if (!isset($ordersByAgent[$agent_id])) {
                    $ordersByAgent[$agent_id] = [
                        'totalAmount' => 0,
                        'items' => []
                    ];
                }

                $qty = (int)$item['quantity'];
                $unitPrice = (float)$med['price']; // enforce backend price

                $ordersByAgent[$agent_id]['totalAmount'] += $qty * $unitPrice;
                $ordersByAgent[$agent_id]['items'][] = [
                    'medicine_id' => $med_id,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice
                ];
            }

            $createdOrders = [];
            $mailer = new Mailer();

            // Fetch patient details
            $stmtP = $this->conn->prepare("SELECT first_name, email FROM users WHERE id = :id");
            $stmtP->execute(['id' => $patient_id]);
            $patient = $stmtP->fetch();

            $emailsToSend = [];

            // Create order for each agent
            foreach ($ordersByAgent as $agent_id => $orderData) {
                $stmtCount = $this->conn->query("SELECT COUNT(*) FROM orders");
                $count = $stmtCount->fetchColumn();
                $orderNo = "ORD-" . time() . "-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                $paymentStatus = in_array($paymentMethod, ['card', 'momo']) ? 'paid' : 'unpaid';

                $order_id = "ORD-ID-" . time() . "-" . bin2hex(random_bytes(4));
                
                $stmt = $this->conn->prepare("INSERT INTO orders (id, order_no, patient_id, agent_id, total_amount, amount_due, payment_method, payment_status, delivery_address) VALUES (:id, :on, :pid, :aid, :total, :due, :pm, :ps, :da)");
                $stmt->execute([
                    'id' => $order_id,
                    'on' => $orderNo,
                    'pid' => $patient_id,
                    'aid' => $agent_id,
                    'total' => $orderData['totalAmount'],
                    'due' => $orderData['totalAmount'], // No NHIS deduction simple case
                    'pm' => $paymentMethod,
                    'ps' => $paymentStatus,
                    'da' => $deliveryAddress
                ]);
                
                // Insert items
                foreach ($orderData['items'] as $oi) {
                    $item_id = "OI-" . time() . "-" . bin2hex(random_bytes(4));
                    $stmtItem = $this->conn->prepare("INSERT INTO order_items (id, order_id, medicine_id, quantity, unit_price) VALUES (:id, :oid, :mid, :qty, :up)");
                    $stmtItem->execute([
                        'id' => $item_id,
                        'oid' => $order_id,
                        'mid' => $oi['medicine_id'],
                        'qty' => $oi['quantity'],
                        'up' => $oi['unitPrice']
                    ]);
                }

                // Fetch pharmacy details
                $stmtA = $this->conn->prepare("SELECT a.pharmacy_name, u.email FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = :aid");
                $stmtA->execute(['aid' => $agent_id]);
                $pharmacy = $stmtA->fetch();

                if ($patient) {
                    $emailsToSend[] = ['type' => 'patient', 'email' => $patient['email'], 'name' => $patient['first_name'], 'orderNo' => $orderNo, 'total' => $orderData['totalAmount']];
                }
                if ($pharmacy) {
                    $emailsToSend[] = ['type' => 'pharmacy', 'email' => $pharmacy['email'], 'name' => $pharmacy['pharmacy_name'], 'orderNo' => $orderNo, 'total' => $orderData['totalAmount']];
                }

                $createdOrders[] = $orderNo;
            }

            $this->conn->commit();

            // Send emails first to guarantee delivery
            foreach ($emailsToSend as $e) {
                if ($e['type'] === 'patient') {
                    $mailer->sendOrderPlacedPatient($e['email'], $e['name'], $e['orderNo'], $e['total']);
                } else {
                    $mailer->sendOrderPlacedPharmacy($e['email'], $e['name'], $e['orderNo'], $e['total']);
                }
            }

            echo json_encode(["success" => true, "message" => "Order(s) placed successfully", "orders" => $createdOrders]);
            exit;

        } catch (Throwable $e) {
            $this->conn->rollBack();
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Debug Error: " . $e->getMessage() . " on line " . $e->getLine()]);
        }
    }

    private function reviewNhis() {
        $decoded = Jwt::authenticate();
        if ($decoded['role'] !== 'pharmacy') {
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Only pharmacies can review NHIS details."]);
            return;
        }

        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['patient_id']) || !isset($input['status']) || !in_array($input['status'], ['approved', 'declined'])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Valid patient_id and status (approved/declined) are required."]);
            return;
        }

        try {
            $stmt = $this->conn->prepare("UPDATE users SET nhis_status = :status WHERE id = :id AND nhis_status = 'pending'");
            $stmt->execute(['status' => $input['status'], 'id' => $input['patient_id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "NHIS status updated successfully."]);
            } else {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Could not update NHIS status (it may already be reviewed or does not exist)."]);
            }
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    }
}
?>
