<?php
// config/Database.php

class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $host = Env::get('DB_HOST', 'localhost');
            $db_name = Env::get('DB_NAME', 'PharmaTrust');
            $username = Env::get('DB_USER', 'root');
            $password = Env::get('DB_PASS', '');

            $this->conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
            $this->conn->exec("set names utf8mb4");
            // Set error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Return arrays by default
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo json_encode(["success" => false, "message" => "Database connection error: " . $exception->getMessage()]);
            exit;
        }

        return $this->conn;
    }
}
?>
