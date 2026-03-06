<?php
/**
 * Toggle User Status - Admin Perpustakaan Digital Gamifikasi
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

if ($user_id === 0 || $user_id == $_SESSION['user_id']) {
    $_SESSION['error_message'] = "Tidak dapat menonaktifkan akun sendiri!";
    header('Location: data_user.php');
    exit();
}

// Cek apakah kolom is_active ada
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");

if ($check_column->num_rows > 0) {
    // Get current status
    $query = "SELECT is_active FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        $new_status = $user['is_active'] ? 0 : 1;
        $action = $user['is_active'] ? 'dinonaktifkan' : 'diaktifkan';
        
        $update = "UPDATE users SET is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($update);
        $stmt->bind_param("ii", $new_status, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User berhasil $action!";
        } else {
            $_SESSION['error_message'] = "Gagal mengubah status user!";
        }
    } else {
        $_SESSION['error_message'] = "User tidak ditemukan!";
    }
} else {
    $_SESSION['error_message'] = "Fitur status user tidak tersedia!";
}

header('Location: data_user.php');
exit();
?>