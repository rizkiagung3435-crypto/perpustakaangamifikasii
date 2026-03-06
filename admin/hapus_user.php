<?php
/**
 * Hapus User - Admin Perpustakaan Digital Gamifikasi
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
    $_SESSION['error_message'] = "Tidak dapat menghapus akun sendiri!";
    header('Location: data_user.php');
    exit();
}

// Get user data before deleting
$query = "SELECT nama, role FROM users WHERE id = ?";
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

// Check if user has active borrowings
$check_borrow = "SELECT COUNT(*) as total FROM peminjaman WHERE id_user = ? AND status = 'dipinjam'";
$stmt = $conn->prepare($check_borrow);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrow_result = $stmt->get_result();
$borrow_data = $borrow_result->fetch_assoc();

if ($borrow_data['total'] > 0) {
    $_SESSION['error_message'] = "User masih memiliki buku yang dipinjam!";
    header('Location: data_user.php');
    exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {
    // Hapus dari admin_details jika user adalah admin
    if ($user['role'] == 'admin') {
        $check_admin_table = $conn->query("SHOW TABLES LIKE 'admin_details'");
        if ($check_admin_table->num_rows > 0) {
            $delete_admin = "DELETE FROM admin_details WHERE user_id = ?";
            $stmt = $conn->prepare($delete_admin);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    }
    
    // Hapus user achievements
    $delete_achievements = "DELETE FROM user_achievement WHERE id_user = ?";
    $stmt = $conn->prepare($delete_achievements);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Hapus user missions
    $delete_missions = "DELETE FROM user_misi WHERE id_user = ?";
    $stmt = $conn->prepare($delete_missions);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Hapus reviews
    $delete_reviews = "DELETE FROM review_buku WHERE id_user = ?";
    $stmt = $conn->prepare($delete_reviews);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Hapus peminjaman yang sudah selesai
    $delete_peminjaman = "DELETE FROM peminjaman WHERE id_user = ? AND status = 'kembali'";
    $stmt = $conn->prepare($delete_peminjaman);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Hapus log aktivitas
    $delete_log = "DELETE FROM log_aktivitas WHERE id_user = ?";
    $stmt = $conn->prepare($delete_log);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Hapus user
    $delete_user = "DELETE FROM users WHERE id = ?";
    $stmt = $conn->prepare($delete_user);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Commit transaksi
    $conn->commit();
    
    $_SESSION['success_message'] = "User <strong>" . htmlspecialchars($user['nama']) . "</strong> berhasil dihapus dari sistem!";
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Gagal menghapus user: " . $e->getMessage();
}

header('Location: data_user.php');
exit();
?>