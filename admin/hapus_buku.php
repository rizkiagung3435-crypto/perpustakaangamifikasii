<?php
/**
 * Halaman Hapus Buku - Admin Perpustakaan Digital Gamifikasi
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

// Get book ID from URL
$id_buku = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_buku === 0) {
    header('Location: data_buku.php');
    exit();
}

// Check if book is being borrowed
$check_query = "SELECT COUNT(*) as total FROM peminjaman WHERE id_buku = ? AND status = 'dipinjam'";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$result = $stmt->get_result();
$borrowed = $result->fetch_assoc();

if ($borrowed['total'] > 0) {
    $_SESSION['error_message'] = "Buku sedang dipinjam, tidak dapat dihapus!";
    header('Location: data_buku.php');
    exit();
}

// Get book cover before deleting
$query = "SELECT cover FROM buku WHERE id_buku = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();

if ($book) {
    // Delete cover file if exists
    if ($book['cover'] && file_exists("../assets/img/covers/" . $book['cover'])) {
        unlink("../assets/img/covers/" . $book['cover']);
    }
    
    // Delete book from database
    $delete_query = "DELETE FROM buku WHERE id_buku = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $id_buku);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Buku berhasil dihapus!";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus buku: " . $conn->error;
    }
} else {
    $_SESSION['error_message'] = "Buku tidak ditemukan!";
}

header('Location: data_buku.php');
exit();
?>