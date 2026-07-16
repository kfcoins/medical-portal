<?php
require 'backend/config/Database.php'; 
$db = (new Database())->getConnection(); 
$stmt = $db->query("SELECT user_id, pharmacy_name FROM agents"); 
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
