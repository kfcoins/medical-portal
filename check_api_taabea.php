<?php
require 'backend/config/Env.php';
Env::load('.env');
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
$stmt = $pdo->prepare("SELECT m.*, a.pharmacy_name, a.allow_pay_on_delivery FROM medicines m LEFT JOIN agents a ON m.agent_id = a.id WHERE m.id = 'MED-1784489555-082a795b'");
$stmt->execute();
$med = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode(['success' => true, 'medicine' => $med]);
