<?php
/**
 * Hapus Review Buku
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

$id_review = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_buku = isset($_GET['buku']) ? (int)$_GET['buku'] : 0;
$id_user = $_SESSION['user_id'];

if ($id_review === 0 || $id_buku === 0) {
    header('Location: katalog.php');
    exit();
}

// Verify ownership
$check_query = "SELECT id_review FROM review_buku WHERE id_review = ? AND id_user = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $id_review, $id_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $query = "DELETE FROM review_buku WHERE id_review = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_review);
    
    if ($stmt->execute()) {
        $_SESSION['review_success'] = "Ulasan berhasil dihapus";
    } else {
        $_SESSION['review_error'] = "Gagal menghapus ulasan";
    }
} else {
    $_SESSION['review_error'] = "Anda tidak memiliki izin untuk menghapus ulasan ini";
}

header("Location: detail_buku.php?id=" . $id_buku);
exit();
?>