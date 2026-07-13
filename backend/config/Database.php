<?php
// config/Database.php

class Database {
    private $host = "localhost";
    private $db_name = "PharmaTrust";
    private $username = "root";
    private $password = ""; // XAMPP default
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
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
