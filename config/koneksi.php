<?php
/**
 * Konfigurasi Database - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "perpustakaan_gamifikasi";
    public $conn;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);

        if ($this->conn->connect_error) {
            die("Koneksi database gagal: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
        return $this->conn;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
}

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>