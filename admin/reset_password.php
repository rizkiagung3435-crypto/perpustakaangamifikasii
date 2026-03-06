<?php
/**
 * Reset Password User - Admin Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    $_SESSION['error_message'] = "ID user tidak valid!";
    header('Location: data_user.php');
    exit();
}

// Get user data for logging
$query = "SELECT nama FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error_message'] = "User tidak ditemukan!";
    header('Location: data_user.php');
    exit();
}

// Default password
$default_password = 'password123';
$hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

$query = "UPDATE users SET password = ? WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    // Log aktivitas
    $log_query = "INSERT INTO log_aktivitas (id_user, aktivitas, deskripsi, ip_address) 
                  VALUES (?, 'reset_password', ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $deskripsi = "Admin mereset password user: " . $user['nama'];
    $log_stmt->bind_param("iss", $_SESSION['user_id'], $deskripsi, $ip);
    $log_stmt->execute();
    
    $_SESSION['success_message'] = "Password user <strong>" . htmlspecialchars($user['nama']) . "</strong> berhasil direset menjadi <strong>password123</strong>!";
} else {
    $_SESSION['error_message'] = "Gagal mereset password user!";
}

header('Location: data_user.php');
exit();
?>