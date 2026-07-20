<?php
require 'backend/config/Env.php';
Env::load('.env');
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
$stmt = $pdo->query("SELECT id, pharmacy_name, allow_pay_on_delivery FROM agents");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
