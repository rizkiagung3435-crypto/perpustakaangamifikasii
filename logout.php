<?php
/**
 * Logout - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

session_start();

// Log aktivitas logout jika tabel tersedia
if (isset($_SESSION['user_id'])) {
    require_once 'config/koneksi.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    $check_log_table = $conn->query("SHOW TABLES LIKE 'log_aktivitas'");
    if ($check_log_table->num_rows > 0) {
        $log_query = "INSERT INTO log_aktivitas (id_user, aktivitas, deskripsi, ip_address, user_agent) 
                      VALUES (?, 'logout', 'User logout', ?, ?)";
        $stmt = $conn->prepare($log_query);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->bind_param("iss", $_SESSION['user_id'], $ip, $ua);
        $stmt->execute();
    }
}

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke halaman login
header('Location: login.php?logout=1');
exit();
?>