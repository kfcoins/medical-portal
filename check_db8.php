<?php
require 'backend/config/Env.php';
Env::load('.env');
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
$stmt = $pdo->query("SELECT m.id, m.agent_id, a.id as agents_id, a.allow_pay_on_delivery FROM medicines m LEFT JOIN agents a ON m.agent_id = a.id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
