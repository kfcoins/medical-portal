<?php
require 'backend/config/Database.php'; 
require 'backend/config/Jwt.php'; 

$uid = '1a67f9de-1b22-45f7-ab20-2663eb96b042'; // PHARMACY
$token = Jwt::encode(['id' => $uid, 'role' => 'pharmacy']);

$ch = curl_init('http://localhost/Mansro/backend/index.php?route=messages/conversations');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);
$res = curl_exec($ch);
curl_close($ch);

echo $res;
