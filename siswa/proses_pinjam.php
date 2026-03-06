<?php
/**
 * Proses Peminjaman Buku
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

$id_buku = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_user = $_SESSION['user_id'];

if ($id_buku === 0) {
    header('Location: katalog.php');
    exit();
}

// Check if book is available
$check_query = "SELECT stok FROM buku WHERE id_buku = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();

if ($book && $book['stok'] > 0) {
    $tanggal_pinjam = date('Y-m-d');
    $tanggal_kembali = date('Y-m-d', strtotime('+7 days'));
    
    // Insert peminjaman
    $query = "INSERT INTO peminjaman (id_user, id_buku, tanggal_pinjam, tanggal_kembali, status) 
              VALUES (?, ?, ?, ?, 'dipinjam')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiss", $id_user, $id_buku, $tanggal_pinjam, $tanggal_kembali);
    
    if ($stmt->execute()) {
        // Update stok
        $update_stok = "UPDATE buku SET stok = stok - 1 WHERE id_buku = ?";
        $stmt = $conn->prepare($update_stok);
        $stmt->bind_param("i", $id_buku);
        $stmt->execute();
        
        // Add XP
        $update_xp = "UPDATE users SET xp = xp + 20 WHERE id = ?";
        $stmt = $conn->prepare($update_xp);
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        
        $_SESSION['borrow_success'] = "Buku berhasil dipinjam! +20 XP";
        header("Location: detail_buku.php?id=" . $id_buku);
        exit();
    }
}

$_SESSION['borrow_error'] = "Gagal meminjam buku";
header("Location: detail_buku.php?id=" . $id_buku);
exit();
?>