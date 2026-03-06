<?php
/**
 * Proses Review Buku
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

$id_buku = isset($_POST['id_buku']) ? (int)$_POST['id_buku'] : 0;
$id_user = $_SESSION['user_id'];
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$komentar = $database->escapeString($_POST['komentar'] ?? '');

if ($id_buku === 0 || $rating < 1 || $rating > 5) {
    header('Location: katalog.php');
    exit();
}

// Check if user has returned this book
$check_query = "SELECT id_pinjam FROM peminjaman 
                WHERE id_user = ? AND id_buku = ? AND status = 'kembali'";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $id_user, $id_buku);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['review_error'] = "Anda harus membaca buku ini terlebih dahulu sebelum memberi ulasan";
    header("Location: detail_buku.php?id=" . $id_buku);
    exit();
}

// Check if user already reviewed
$check_review = "SELECT id_review FROM review_buku WHERE id_user = ? AND id_buku = ?";
$stmt = $conn->prepare($check_review);
$stmt->bind_param("ii", $id_user, $id_buku);
$stmt->execute();
$existing = $stmt->get_result();

if ($existing->num_rows > 0) {
    // Update existing review
    $query = "UPDATE review_buku SET rating = ?, komentar = ?, created_at = NOW() 
              WHERE id_user = ? AND id_buku = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isii", $rating, $komentar, $id_user, $id_buku);
    $message = "Ulasan berhasil diperbarui!";
} else {
    // Insert new review
    $query = "INSERT INTO review_buku (id_user, id_buku, rating, komentar) 
              VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiis", $id_user, $id_buku, $rating, $komentar);
    $message = "Ulasan berhasil ditambahkan! +30 XP";
    
    // Add XP for new review
    $update_xp = "UPDATE users SET xp = xp + 30 WHERE id = ?";
    $xp_stmt = $conn->prepare($update_xp);
    $xp_stmt->bind_param("i", $id_user);
    $xp_stmt->execute();
}

if ($stmt->execute()) {
    $_SESSION['review_success'] = $message;
} else {
    $_SESSION['review_error'] = "Gagal menyimpan ulasan";
}

header("Location: detail_buku.php?id=" . $id_buku);
exit();
?>