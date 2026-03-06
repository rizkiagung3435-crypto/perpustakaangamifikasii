<?php
/**
 * Halaman Katalog Buku - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 * Dengan fitur rating dan komentar per akun
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

// Handle book borrowing
if (isset($_GET['pinjam'])) {
    $id_buku = (int)$_GET['pinjam'];
    $id_user = $_SESSION['user_id'];
    $tanggal_pinjam = date('Y-m-d');
    $tanggal_kembali = date('Y-m-d', strtotime('+7 days'));
    
    // Check if book is available
    $check_query = "SELECT stok FROM buku WHERE id_buku = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $id_buku);
    $stmt->execute();
    $check_result = $stmt->get_result();
    $book = $check_result->fetch_assoc();
    
    if ($book['stok'] > 0) {
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
            
            // Check for achievement
            $check_achievement = "SELECT COUNT(*) as total FROM peminjaman WHERE id_user = ?";
            $stmt = $conn->prepare($check_achievement);
            $stmt->bind_param("i", $id_user);
            $stmt->execute();
            $achievement_result = $stmt->get_result();
            $total_pinjam = $achievement_result->fetch_assoc()['total'];
            
            if ($total_pinjam == 1) {
                // Give First Book achievement
                $ach_query = "INSERT IGNORE INTO user_achievement (id_user, id_achievement) 
                              SELECT ?, id FROM achievement WHERE nama_badge = 'First Book'";
                $stmt = $conn->prepare($ach_query);
                $stmt->bind_param("i", $id_user);
                $stmt->execute();
            }
            
            $_SESSION['borrow_success'] = "Buku berhasil dipinjam! +20 XP";
            header("Location: katalog.php?" . (isset($_GET['search']) ? "search=" . urlencode($_GET['search']) . "&" : "") . (isset($_GET['category']) ? "category=" . urlencode($_GET['category']) : ""));
            exit();
        } else {
            $error = "Gagal meminjam buku";
        }
    } else {
        $error = "Maaf, stok buku sedang kosong";
    }
}

// Handle rating submission
if (isset($_POST['submit_rating'])) {
    $id_buku = (int)$_POST['id_buku'];
    $id_user = $_SESSION['user_id'];
    $rating = (int)$_POST['rating'];
    $komentar = $database->escapeString($_POST['komentar'] ?? '');
    
    // Validate rating
    if ($rating >= 1 && $rating <= 5) {
        // Check if user has borrowed and returned this book
        $check_query = "SELECT id_pinjam FROM peminjaman 
                        WHERE id_user = ? AND id_buku = ? AND status = 'kembali' 
                        LIMIT 1";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ii", $id_user, $id_buku);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Check if user already reviewed this book
            $check_review = "SELECT id_review FROM review_buku 
                            WHERE id_user = ? AND id_buku = ?";
            $stmt = $conn->prepare($check_review);
            $stmt->bind_param("ii", $id_user, $id_buku);
            $stmt->execute();
            $review_exists = $stmt->get_result();
            
            if ($review_exists->num_rows == 0) {
                // Insert review
                $query = "INSERT INTO review_buku (id_user, id_buku, rating, komentar) 
                          VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iiis", $id_user, $id_buku, $rating, $komentar);
                
                if ($stmt->execute()) {
                    // Add XP for reviewing
                    $update_xp = "UPDATE users SET xp = xp + 30 WHERE id = ?";
                    $stmt = $conn->prepare($update_xp);
                    $stmt->bind_param("i", $id_user);
                    $stmt->execute();
                    
                    // Check for review achievement
                    $check_review_count = "SELECT COUNT(*) as total FROM review_buku WHERE id_user = ?";
                    $stmt = $conn->prepare($check_review_count);
                    $stmt->bind_param("i", $id_user);
                    $stmt->execute();
                    $review_count = $stmt->get_result()->fetch_assoc()['total'];
                    
                    if ($review_count == 3) {
                        // Give Book Reviewer achievement
                        $ach_query = "INSERT IGNORE INTO user_achievement (id_user, id_achievement) 
                                      SELECT ?, id FROM achievement WHERE nama_badge = 'Book Reviewer'";
                        $stmt = $conn->prepare($ach_query);
                        $stmt->bind_param("i", $id_user);
                        $stmt->execute();
                    }
                    
                    $_SESSION['review_success'] = "Terima kasih! Rating Anda telah disimpan. +30 XP";
                } else {
                    $_SESSION['review_error'] = "Gagal menyimpan rating";
                }
            } else {
                // Update existing review
                $update_query = "UPDATE review_buku SET rating = ?, komentar = ?, created_at = CURRENT_TIMESTAMP 
                               WHERE id_user = ? AND id_buku = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("isii", $rating, $komentar, $id_user, $id_buku);
                
                if ($stmt->execute()) {
                    $_SESSION['review_success'] = "Ulasan Anda telah diperbarui!";
                } else {
                    $_SESSION['review_error'] = "Gagal memperbarui ulasan";
                }
            }
        } else {
            $_SESSION['review_error'] = "Anda harus meminjam dan membaca buku ini terlebih dahulu sebelum memberi rating";
        }
    } else {
        $_SESSION['review_error'] = "Rating tidak valid";
    }
    
    // Build redirect URL
    $redirect_url = "katalog.php?" . 
                    (isset($_GET['search']) ? "search=" . urlencode($_GET['search']) . "&" : "") . 
                    (isset($_GET['category']) ? "category=" . urlencode($_GET['category']) . "&" : "") . 
                    "id_buku=$id_buku";
    header("Location: $redirect_url");
    exit();
}

// Handle delete review
if (isset($_GET['delete_review']) && isset($_GET['id_buku'])) {
    $id_review = (int)$_GET['delete_review'];
    $id_buku = (int)$_GET['id_buku'];
    $id_user = $_SESSION['user_id'];
    
    // Verify that the review belongs to the user
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
    
    $redirect_url = "katalog.php?" . 
                    (isset($_GET['search']) ? "search=" . urlencode($_GET['search']) . "&" : "") . 
                    (isset($_GET['category']) ? "category=" . urlencode($_GET['category']) . "&" : "") . 
                    "id_buku=$id_buku";
    header("Location: $redirect_url");
    exit();
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $database->escapeString($_GET['search']) : '';
$category = isset($_GET['category']) ? $database->escapeString($_GET['category']) : '';
$highlight_book = isset($_GET['id_buku']) ? (int)$_GET['id_buku'] : 0;

// Build query with prepared statements
$query = "SELECT b.*, 
          COALESCE(AVG(r.rating), 0) as avg_rating,
          COUNT(r.id_review) as total_review
          FROM buku b
          LEFT JOIN review_buku r ON b.id_buku = r.id_buku
          WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $query .= " AND (b.judul LIKE ? OR b.penulis LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}
if ($category) {
    $query .= " AND b.kategori = ?";
    $params[] = $category;
    $types .= "s";
}
$query .= " GROUP BY b.id_buku ORDER BY b.judul ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();

// Get unique categories for filter
$categories = $conn->query("SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL ORDER BY kategori");

// Get all reviews for books
$reviews = [];
$query_reviews = "SELECT r.*, u.nama, u.avatar 
                  FROM review_buku r
                  JOIN users u ON r.id_user = u.id
                  ORDER BY r.created_at DESC";
$result = $conn->query($query_reviews);
while ($row = $result->fetch_assoc()) {
    $reviews[$row['id_buku']][] = $row;
}

// Get user's reviews
$user_id = $_SESSION['user_id'];
$user_reviews = [];
$query_user_reviews = "SELECT id_buku, id_review, rating, komentar FROM review_buku WHERE id_user = ?";
$stmt = $conn->prepare($query_user_reviews);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_reviews[$row['id_buku']] = [
        'id_review' => $row['id_review'],
        'rating' => $row['rating'],
        'komentar' => $row['komentar']
    ];
}

// Get books that user has borrowed and returned
$borrowed_books = [];
$query_borrowed = "SELECT DISTINCT id_buku FROM peminjaman 
                   WHERE id_user = ? AND status = 'kembali'";
$stmt = $conn->prepare($query_borrowed);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $borrowed_books[] = $row['id_buku'];
}

// Get session messages
$borrow_success = $_SESSION['borrow_success'] ?? '';
$borrow_error = $_SESSION['borrow_error'] ?? '';
$review_success = $_SESSION['review_success'] ?? '';
$review_error = $_SESSION['review_error'] ?? '';
unset($_SESSION['borrow_success'], $_SESSION['borrow_error'], $_SESSION['review_success'], $_SESSION['review_error']);

// Page title
$page_title = "Katalog Buku - Perpustakaan Digital";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .bg-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .bg-shape:nth-child(1) {
            width: 400px;
            height: 400px;
            top: -200px;
            right: -200px;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
        }

        .bg-shape:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -150px;
            left: -150px;
            background: linear-gradient(135deg, #48dbfb, #1dd1a1);
            animation-delay: 2s;
        }

        .bg-shape:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 30%;
            right: 10%;
            background: linear-gradient(135deg, #f368e0, #ff9f43);
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
        }

        /* Floating Books */
        .floating-book {
            position: absolute;
            font-size: 3rem;
            opacity: 0.1;
            animation: floatBook 15s linear infinite;
        }

        .floating-book:nth-child(6) { top: 15%; left: 5%; animation-delay: 0s; }
        .floating-book:nth-child(7) { top: 75%; left: 85%; animation-delay: 3s; }
        .floating-book:nth-child(8) { top: 45%; left: 92%; animation-delay: 6s; }

        @keyframes floatBook {
            0% { transform: translateY(0) rotate(0deg); opacity: 0.1; }
            50% { transform: translateY(-100px) rotate(10deg); opacity: 0.2; }
            100% { transform: translateY(0) rotate(0deg); opacity: 0.1; }
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo a {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            animation: pulse 2s infinite;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 10px;
        }

        .nav-link {
            color: #666;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .theme-toggle-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: #666;
        }

        .theme-toggle-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: rotate(180deg);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
            position: relative;
            z-index: 1;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 50px rgba(102, 126, 234, 0.2);
        }

        /* Header Section */
        .header-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .header-section h1 {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header-section p {
            color: #666;
            font-size: 16px;
        }

        /* Search and Filter */
        .search-section {
            margin-bottom: 30px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: center;
        }

        .form-group {
            position: relative;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            transition: all 0.3s ease;
            font-size: 16px;
            z-index: 1;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        .form-control:focus + i {
            color: #667eea;
            transform: translateY(-50%) scale(1.1);
        }

        select.form-control {
            padding: 12px 15px 12px 45px;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary i {
            font-size: 14px;
        }

        /* Category Filters */
        .category-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .category-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #e0e0e0;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #666;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .category-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        .category-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: white;
        }

        .category-btn i {
            font-size: 14px;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.5s ease;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fff4, #c6f6d5);
            border: 1px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: linear-gradient(135deg, #fff5f5, #fed7d7);
            border: 1px solid #feb2b2;
            color: #c53030;
        }

        .alert i {
            font-size: 20px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .book-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .book-card.highlight {
            border: 3px solid #fbbf24;
            box-shadow: 0 0 30px rgba(251, 191, 36, 0.5);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 30px rgba(251, 191, 36, 0.5);
            }
            50% {
                box-shadow: 0 0 50px rgba(251, 191, 36, 0.8);
            }
        }

        .book-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .book-card:hover::before {
            transform: translateX(0);
        }

        .book-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.3);
        }

        .book-cover-wrapper {
            position: relative;
            margin-bottom: 15px;
            border-radius: 15px;
            overflow: hidden;
            height: 200px;
        }

        .book-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .book-card:hover .book-cover {
            transform: scale(1.1);
        }

        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 1;
        }

        .stock-badge.available {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .stock-badge.unavailable {
            background: linear-gradient(135deg, #f56565, #c53030);
            color: white;
        }

        /* Rating Stars */
        .rating-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            flex-wrap: wrap;
        }

        .stars {
            display: flex;
            gap: 3px;
        }

        .star {
            color: #d1d5db;
            font-size: 16px;
        }

        .star.filled {
            color: #fbbf24;
        }

        .star.half-filled {
            position: relative;
            color: #d1d5db;
        }

        .star.half-filled::before {
            content: '\f005';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 0;
            width: 50%;
            overflow: hidden;
            color: #fbbf24;
        }

        .rating-value {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
        }

        .rating-count {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
        }

        .book-info {
            padding: 10px 0;
        }

        .book-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.3;
            cursor: pointer;
        }

        .book-title:hover {
            color: #667eea;
        }

        .book-author {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .book-category {
            display: inline-block;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .book-category:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        .book-stock {
            font-size: 13px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .book-stock i {
            font-size: 14px;
        }

        .stock-high {
            color: #48bb78;
        }

        .stock-low {
            color: #f56565;
        }

        .book-description {
            font-size: 13px;
            color: #666;
            margin: 10px 0;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Reviews Section */
        .reviews-section {
            margin-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 15px;
        }

        .reviews-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .reviews-title:hover {
            color: #667eea;
        }

        .reviews-title i {
            color: #667eea;
        }

        .review-item {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            position: relative;
        }

        .review-item.own-review {
            background: rgba(251, 191, 36, 0.1);
            border-left: 3px solid #fbbf24;
        }

        .review-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .review-avatar {
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }

        .review-user {
            flex: 1;
        }

        .review-name {
            font-weight: 600;
            color: #333;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .review-badge {
            font-size: 10px;
            background: #fbbf24;
            color: #1a1a2e;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        .review-date {
            font-size: 11px;
            color: #999;
        }

        .review-rating {
            display: flex;
            gap: 2px;
            margin-bottom: 8px;
        }

        .review-rating i {
            color: #fbbf24;
            font-size: 12px;
        }

        .review-comment {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
            margin-bottom: 8px;
            font-style: italic;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 5px;
        }

        .btn-edit-review,
        .btn-delete-review {
            background: none;
            border: none;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 3px;
            padding: 3px 8px;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .btn-edit-review {
            color: #667eea;
        }

        .btn-edit-review:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .btn-delete-review {
            color: #f56565;
        }

        .btn-delete-review:hover {
            background: rgba(245, 101, 101, 0.1);
        }

        .no-reviews {
            text-align: center;
            padding: 15px;
            color: #999;
            font-size: 13px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 10px;
        }

        .btn-borrow {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            margin-top: 15px;
        }

        .btn-borrow:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-borrow.disabled {
            background: linear-gradient(135deg, #a0aec0, #718096);
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-review {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1a1a2e;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            margin-top: 10px;
        }

        .btn-review:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(251, 191, 36, 0.4);
        }

        .btn-review.disabled {
            background: linear-gradient(135deg, #a0aec0, #718096);
            opacity: 0.6;
            cursor: not-allowed;
            color: white;
        }

        .btn-review i {
            font-size: 14px;
        }

        .btn-detail {
            width: 100%;
            padding: 12px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            margin-top: 10px;
        }

        .btn-detail:hover {
            background: rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        /* Review Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 30px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            position: relative;
            animation: slideUp 0.5s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            color: #f56565;
            transform: rotate(90deg);
        }

        .modal-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .modal-book-title {
            font-size: 18px;
            font-weight: 600;
            color: #667eea;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
        }

        .rating-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .rating-star {
            font-size: 40px;
            color: #d1d5db;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .rating-star:hover,
        .rating-star.selected {
            color: #fbbf24;
            transform: scale(1.1);
        }

        .rating-label {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            resize: vertical;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 30px;
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
            animation: bounce 2s infinite;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading.active {
            display: flex;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Floating Animation */
        .floating {
            animation: floating 3s ease infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Fade In Animation */
        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        /* Tooltip */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 8px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .books-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .books-grid {
                grid-template-columns: 1fr;
            }
            
            .main-container {
                padding: 15px;
            }
            
            .glass-card {
                padding: 20px;
            }
            
            .header-section h1 {
                font-size: 28px;
            }

            .rating-selector {
                gap: 5px;
            }

            .rating-star {
                font-size: 30px;
            }
        }

        @media (max-width: 480px) {
            .category-filters {
                justify-content: center;
            }
            
            .category-btn {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                padding: 25px;
            }

            .rating-star {
                font-size: 25px;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }

        body.dark-mode .glass-card,
        body.dark-mode .book-card,
        body.dark-mode .navbar,
        body.dark-mode .empty-state,
        body.dark-mode .modal-content {
            background: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .form-control {
            background: #2d3748;
            border-color: #4a5568;
            color: white;
        }

        body.dark-mode .form-control option {
            background: #2d3748;
        }

        body.dark-mode .book-title,
        body.dark-mode .header-section h1,
        body.dark-mode .empty-state h3,
        body.dark-mode .modal-title,
        body.dark-mode .reviews-title,
        body.dark-mode .review-name {
            color: white;
        }

        body.dark-mode .book-author,
        body.dark-mode .book-description,
        body.dark-mode .header-section p,
        body.dark-mode .empty-state p,
        body.dark-mode .form-control::placeholder,
        body.dark-mode .rating-label,
        body.dark-mode .modal-book-title,
        body.dark-mode .review-comment,
        body.dark-mode .review-date,
        body.dark-mode .rating-count {
            color: #a0aec0;
        }

        body.dark-mode .modal-book-title {
            background: rgba(102, 126, 234, 0.2);
        }

        body.dark-mode .review-item {
            background: rgba(255, 255, 255, 0.05);
        }

        body.dark-mode .review-item.own-review {
            background: rgba(251, 191, 36, 0.15);
        }

        body.dark-mode .no-reviews {
            background: rgba(255, 255, 255, 0.02);
            color: #a0aec0;
        }

        body.dark-mode .reviews-section {
            border-top-color: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
        <div class="floating-book">📚</div>
        <div class="floating-book">📖</div>
        <div class="floating-book">📕</div>
    </div>

    <!-- Loading Animation -->
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <!-- Review Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeReviewModal()">&times;</span>
            <h2 class="modal-title">Beri Ulasan Buku</h2>
            <div class="modal-book-title" id="modalBookTitle"></div>
            
            <form method="POST" action="" id="reviewForm">
                <input type="hidden" name="id_buku" id="reviewBookId">
                <input type="hidden" name="rating" id="selectedRating" value="0">
                
                <div class="rating-selector" id="ratingSelector">
                    <i class="fas fa-star rating-star" data-rating="1"></i>
                    <i class="fas fa-star rating-star" data-rating="2"></i>
                    <i class="fas fa-star rating-star" data-rating="3"></i>
                    <i class="fas fa-star rating-star" data-rating="4"></i>
                    <i class="fas fa-star rating-star" data-rating="5"></i>
                </div>
                
                <div class="rating-label" id="ratingLabel">
                    Pilih rating untuk buku ini
                </div>
                
                <div class="form-group">
                    <label for="komentar">Komentar <span style="color: #999; font-weight: normal;">(opsional)</span></label>
                    <textarea class="form-control" 
                              id="komentar" 
                              name="komentar" 
                              rows="4" 
                              placeholder="Tulis pengalaman Anda membaca buku ini..."></textarea>
                </div>
                
                <button type="submit" name="submit_rating" class="btn-submit">
                    <i class="fas fa-star"></i>
                    Kirim Ulasan
                </button>
            </form>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../dashboard.php">
                    <div class="logo-icon">📚</div>
                    <span class="logo-text">Perpustakaan Digital</span>
                </a>
            </div>
            
            <ul class="nav-links">
                <li class="nav-item">
                    <a href="../dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="katalog.php" class="nav-link active">
                        <i class="fas fa-book"></i>
                        <span>Katalog</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="leaderboard.php" class="nav-link">
                        <i class="fas fa-trophy"></i>
                        <span>Leaderboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="misi.php" class="nav-link">
                        <i class="fas fa-tasks"></i>
                        <span>Misi</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profil.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span>Profil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
                <li class="nav-item">
                    <button id="theme-toggle" class="theme-toggle-btn" title="Ganti Tema">
                        <i class="fas fa-moon"></i>
                    </button>
                </li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <!-- Header -->
        <div class="header-section glass-card fade-in floating">
            <h1>📚 Katalog Buku</h1>
            <p>Temukan buku favoritmu dan berikan ulasan!</p>
        </div>

        <!-- Search and Filter -->
        <div class="search-section glass-card fade-in">
            <form method="GET" action="" class="search-form">
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Cari judul atau penulis..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-tags"></i>
                        <select name="category" class="form-control">
                            <option value="">Semua Kategori</option>
                            <?php 
                            $categories->data_seek(0);
                            while($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($cat['kategori']); ?>" 
                                        <?php echo $category == $cat['kategori'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['kategori']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i>
                    Cari
                </button>
            </form>
        </div>

        <!-- Category Filters -->
        <div class="category-filters">
            <button class="category-btn <?php echo !$category ? 'active' : ''; ?>" 
                    onclick="window.location.href='?search=<?php echo urlencode($search); ?>'">
                <i class="fas fa-layer-group"></i>
                Semua
            </button>
            <?php 
            $categories->data_seek(0);
            while($cat = $categories->fetch_assoc()): 
            ?>
                <button class="category-btn <?php echo $category == $cat['kategori'] ? 'active' : ''; ?>" 
                        onclick="window.location.href='?category=<?php echo urlencode($cat['kategori']); ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>'">
                    <i class="fas fa-tag"></i>
                    <?php echo htmlspecialchars($cat['kategori']); ?>
                </button>
            <?php endwhile; ?>
        </div>

        <!-- Alerts -->
        <?php if ($borrow_success): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?php echo $borrow_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($borrow_error): ?>
            <div class="alert alert-error fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $borrow_error; ?>
            </div>
        <?php endif; ?>

        <?php if ($review_success): ?>
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle"></i>
                <?php echo $review_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($review_error): ?>
            <div class="alert alert-error fade-in">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $review_error; ?>
            </div>
        <?php endif; ?>

        <!-- Books Grid -->
        <div class="books-grid">
            <?php if ($books->num_rows > 0): ?>
                <?php while($book = $books->fetch_assoc()): 
                    $can_review = in_array($book['id_buku'], $borrowed_books) && !isset($user_reviews[$book['id_buku']]);
                    $has_reviewed = isset($user_reviews[$book['id_buku']]);
                    $user_review = $user_reviews[$book['id_buku']] ?? null;
                    $avg_rating = round($book['avg_rating'], 1);
                    $total_review = $book['total_review'];
                    $book_reviews = $reviews[$book['id_buku']] ?? [];
                ?>
                    <div class="book-card fade-in <?php echo $book['id_buku'] == $highlight_book ? 'highlight' : ''; ?>" 
                         data-category="<?php echo htmlspecialchars($book['kategori']); ?>"
                         id="book-<?php echo $book['id_buku']; ?>"
                         onclick="window.location.href='detail_buku.php?id=<?php echo $book['id_buku']; ?>'">
                        <div class="book-cover-wrapper">
                            <img src="../assets/img/covers/<?php echo $book['cover'] ?: 'default-book.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($book['judul']); ?>" 
                                 class="book-cover"
                                 loading="lazy">
                            <?php if ($book['stok'] > 0): ?>
                                <span class="stock-badge available">
                                    <i class="fas fa-check-circle"></i> Tersedia
                                </span>
                            <?php else: ?>
                                <span class="stock-badge unavailable">
                                    <i class="fas fa-times-circle"></i> Habis
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="book-info">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['judul']); ?></h3>
                            <p class="book-author">
                                <i class="fas fa-pen-fancy"></i>
                                <?php echo htmlspecialchars($book['penulis']); ?>
                            </p>
                            
                            <!-- Rating Display -->
                            <div class="rating-container">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= floor($avg_rating)): ?>
                                            <i class="fas fa-star star filled"></i>
                                        <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                            <i class="fas fa-star-half-alt star half-filled"></i>
                                        <?php else: ?>
                                            <i class="far fa-star star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="rating-value">
                                    <?php echo $avg_rating > 0 ? number_format($avg_rating, 1) : '0.0'; ?>
                                    <span class="rating-count">(<?php echo $total_review; ?> ulasan)</span>
                                </div>
                            </div>
                            
                            <?php if ($book['kategori']): ?>
                                <span class="book-category">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($book['kategori']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <div class="book-stock <?php echo $book['stok'] > 5 ? 'stock-high' : 'stock-low'; ?>">
                                <i class="fas fa-boxes"></i>
                                Stok: <?php echo $book['stok']; ?> buku
                            </div>
                            
                            <?php if ($book['deskripsi']): ?>
                                <p class="book-description">
                                    <?php echo htmlspecialchars(substr($book['deskripsi'], 0, 100)) . '...'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Reviews Section -->
                        <div class="reviews-section" onclick="event.stopPropagation();">
                            <div class="reviews-title" onclick="window.location.href='detail_buku.php?id=<?php echo $book['id_buku']; ?>#reviews'">
                                <i class="fas fa-comments"></i>
                                Ulasan (<?php echo $total_review; ?>)
                            </div>
                            
                            <?php if (!empty($book_reviews)): ?>
                                <?php 
                                $display_reviews = array_slice($book_reviews, 0, 2);
                                foreach($display_reviews as $review): 
                                    $is_own_review = ($review['id_user'] == $_SESSION['user_id']);
                                ?>
                                    <div class="review-item <?php echo $is_own_review ? 'own-review' : ''; ?>" onclick="event.stopPropagation();">
                                        <div class="review-header">
                                            <div class="review-avatar">
                                                <?php echo strtoupper(substr($review['nama'], 0, 1)); ?>
                                            </div>
                                            <div class="review-user">
                                                <div class="review-name">
                                                    <?php echo htmlspecialchars($review['nama']); ?>
                                                    <?php if ($is_own_review): ?>
                                                        <span class="review-badge">Ulasanmu</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="review-date">
                                                    <?php 
                                                    $date = new DateTime($review['created_at']);
                                                    $now = new DateTime();
                                                    $diff = $now->diff($date);
                                                    
                                                    if ($diff->d < 1 && $diff->h < 1 && $diff->i < 1) {
                                                        echo "Baru saja";
                                                    } elseif ($diff->d < 1 && $diff->h < 1) {
                                                        echo $diff->i . " menit yang lalu";
                                                    } elseif ($diff->d < 1) {
                                                        echo $diff->h . " jam yang lalu";
                                                    } elseif ($diff->d < 7) {
                                                        echo $diff->d . " hari yang lalu";
                                                    } else {
                                                        echo date('d M Y', strtotime($review['created_at']));
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#fbbf24' : '#d1d5db'; ?>;"></i>
                                            <?php endfor; ?>
                                        </div>
                                        
                                        <?php if ($review['komentar']): ?>
                                            <div class="review-comment">
                                                "<?php echo htmlspecialchars($review['komentar']); ?>"
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_own_review): ?>
                                        <div class="review-actions" onclick="event.stopPropagation();">
                                            <button class="btn-edit-review" onclick="editReview(<?php echo $book['id_buku']; ?>, '<?php echo htmlspecialchars($book['judul']); ?>', <?php echo $review['rating']; ?>, '<?php echo htmlspecialchars(addslashes($review['komentar'])); ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="?delete_review=<?php echo $review['id_review']; ?>&id_buku=<?php echo $book['id_buku']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" 
                                               class="btn-delete-review"
                                               onclick="return confirmDeleteReview(event)">
                                                <i class="fas fa-trash"></i> Hapus
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($book_reviews) > 2): ?>
                                    <div style="text-align: center; margin-top: 10px;" onclick="window.location.href='detail_buku.php?id=<?php echo $book['id_buku']; ?>#reviews'">
                                        <small style="color: #667eea; cursor: pointer;">+<?php echo count($book_reviews) - 2; ?> ulasan lainnya</small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-reviews" onclick="window.location.href='detail_buku.php?id=<?php echo $book['id_buku']; ?>#reviews'">
                                    <i class="far fa-comment"></i> Belum ada ulasan untuk buku ini
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons" onclick="event.stopPropagation();">
                            <?php if ($book['stok'] > 0): ?>
                                <a href="?pinjam=<?php echo $book['id_buku']; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $category ? '&category='.urlencode($category) : ''; ?>" 
                                   class="btn-borrow" 
                                   data-book-title="<?php echo htmlspecialchars($book['judul']); ?>"
                                   onclick="return confirmBorrow(event, '<?php echo htmlspecialchars($book['judul']); ?>')">
                                    <i class="fas fa-book-open"></i>
                                    Pinjam Buku
                                </a>
                            <?php else: ?>
                                <button class="btn-borrow disabled" disabled>
                                    <i class="fas fa-clock"></i>
                                    Stok Habis
                                </button>
                            <?php endif; ?>
                            
                            <!-- Review Button -->
                            <?php if ($can_review): ?>
                                <button class="btn-review" onclick="openReviewModal(<?php echo $book['id_buku']; ?>, '<?php echo htmlspecialchars($book['judul']); ?>')">
                                    <i class="fas fa-star"></i>
                                    Tulis Ulasan
                                </button>
                            <?php elseif ($has_reviewed): ?>
                                <button class="btn-review" onclick="editReview(<?php echo $book['id_buku']; ?>, '<?php echo htmlspecialchars($book['judul']); ?>', <?php echo $user_review['rating']; ?>, '<?php echo htmlspecialchars(addslashes($user_review['komentar'])); ?>')">
                                    <i class="fas fa-edit"></i>
                                    Edit Ulasan
                                </button>
                            <?php elseif ($book['stok'] > 0): ?>
                                <button class="btn-review disabled" disabled data-tooltip="Pinjam dan baca buku ini terlebih dahulu untuk memberi ulasan">
                                    <i class="fas fa-lock"></i>
                                    Ulasan Terkunci
                                </button>
                            <?php endif; ?>
                            
                            <a href="detail_buku.php?id=<?php echo $book['id_buku']; ?>" class="btn-detail">
                                <i class="fas fa-info-circle"></i>
                                Detail
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state fade-in">
                    <div class="empty-icon">📚</div>
                    <h3>Tidak Ada Buku Ditemukan</h3>
                    <p>Maaf, tidak ada buku yang sesuai dengan pencarianmu.</p>
                    <a href="katalog.php" class="btn-primary">
                        <i class="fas fa-sync-alt"></i>
                        Reset Filter
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Confirm borrow
        function confirmBorrow(event, bookTitle) {
            event.stopPropagation();
            return confirm(`Apakah Anda yakin ingin meminjam buku "${bookTitle}"? Anda akan mendapatkan +20 XP!`);
        }

        // Confirm delete review
        function confirmDeleteReview(event) {
            event.preventDefault();
            event.stopPropagation();
            
            Swal.fire({
                title: 'Hapus Ulasan?',
                text: 'Ulasan yang dihapus tidak dapat dikembalikan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f56565',
                cancelButtonColor: '#a0aec0',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                background: document.body.classList.contains('dark-mode') ? '#1a1a2e' : '#fff',
                color: document.body.classList.contains('dark-mode') ? '#fff' : '#333'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = event.currentTarget.href;
                }
            });
            
            return false;
        }

        // Review Modal Functions
        const reviewModal = document.getElementById('reviewModal');
        const ratingStars = document.querySelectorAll('.rating-star');
        const ratingLabel = document.getElementById('ratingLabel');
        const selectedRating = document.getElementById('selectedRating');
        const modalBookTitle = document.getElementById('modalBookTitle');
        const reviewBookId = document.getElementById('reviewBookId');
        const komentarField = document.getElementById('komentar');

        function openReviewModal(bookId, bookTitle, rating = 0, komentar = '') {
            event.stopPropagation();
            reviewBookId.value = bookId;
            modalBookTitle.textContent = `"${bookTitle}"`;
            selectedRating.value = rating;
            komentarField.value = komentar;
            
            // Reset and set stars
            if (rating > 0) {
                highlightStars(rating);
                const labels = ['Buruk', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'];
                ratingLabel.textContent = `Anda memilih: ${labels[rating-1]} (${rating} bintang)`;
            } else {
                resetStars();
                ratingLabel.textContent = 'Pilih rating untuk buku ini';
            }
            
            reviewModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function editReview(bookId, bookTitle, rating, komentar) {
            event.stopPropagation();
            openReviewModal(bookId, bookTitle, rating, komentar);
        }

        function closeReviewModal() {
            reviewModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Rating star selection
        ratingStars.forEach(star => {
            star.addEventListener('click', function(e) {
                e.stopPropagation();
                const rating = this.dataset.rating;
                selectedRating.value = rating;
                highlightStars(rating);
                
                // Update label
                const labels = ['Buruk', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'];
                ratingLabel.textContent = `Anda memilih: ${labels[rating-1]} (${rating} bintang)`;
            });

            star.addEventListener('mouseenter', function(e) {
                e.stopPropagation();
                const rating = this.dataset.rating;
                highlightStars(rating);
            });

            star.addEventListener('mouseleave', function(e) {
                e.stopPropagation();
                const currentRating = selectedRating.value;
                if (currentRating > 0) {
                    highlightStars(currentRating);
                } else {
                    resetStars();
                }
            });
        });

        function highlightStars(rating) {
            ratingStars.forEach(star => {
                const starRating = star.dataset.rating;
                if (starRating <= rating) {
                    star.classList.add('selected');
                } else {
                    star.classList.remove('selected');
                }
            });
        }

        function resetStars() {
            ratingStars.forEach(star => {
                star.classList.remove('selected');
            });
        }

        // Form validation
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const rating = selectedRating.value;
            if (rating == 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Rating Belum Dipilih',
                    text: 'Silakan pilih rating untuk buku ini',
                    confirmButtonColor: '#667eea',
                    background: document.body.classList.contains('dark-mode') ? '#1a1a2e' : '#fff',
                    color: document.body.classList.contains('dark-mode') ? '#fff' : '#333'
                });
                return false;
            }
            this.submit();
        });

        // Close modal when clicking outside
        reviewModal.addEventListener('click', function(e) {
            if (e.target === reviewModal) {
                closeReviewModal();
            }
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                document.body.classList.toggle('dark-mode');
                const icon = this.querySelector('i');
                if (document.body.classList.contains('dark-mode')) {
                    icon.className = 'fas fa-sun';
                    localStorage.setItem('theme', 'dark');
                } else {
                    icon.className = 'fas fa-moon';
                    localStorage.setItem('theme', 'light');
                }
                
                this.style.transform = 'rotate(180deg)';
                setTimeout(() => {
                    this.style.transform = 'rotate(0)';
                }, 300);
            });

            // Check for saved theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                themeToggle.querySelector('i').className = 'fas fa-sun';
            }
        }

        // Loading animation on form submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                if (this.id !== 'reviewForm' || selectedRating.value > 0) {
                    document.getElementById('loading').classList.add('active');
                }
            });
        });

        // Loading animation on category filter click
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!this.classList.contains('active')) {
                    document.getElementById('loading').classList.add('active');
                }
            });
        });

        // Auto hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });

        // Parallax effect on mouse move
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.bg-shape');
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 30;
                const x = (window.innerWidth - mouseX * speed) / 100;
                const y = (window.innerHeight - mouseY * speed) / 100;
                shape.style.transform = `translate(${x}px, ${y}px)`;
            });
        });

        // Smooth scroll to top
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

        // Add floating animation to cards
        const cards = document.querySelectorAll('.book-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press '/' to focus search
            if (e.key === '/') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            // Press 'Esc' to clear search or close modal
            if (e.key === 'Escape') {
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput === document.activeElement) {
                    searchInput.value = '';
                }
                if (reviewModal.classList.contains('active')) {
                    closeReviewModal();
                }
            }
        });

        // Highlight book if ID is in URL
        <?php if ($highlight_book): ?>
        setTimeout(() => {
            const highlightedBook = document.getElementById('book-<?php echo $highlight_book; ?>');
            if (highlightedBook) {
                highlightedBook.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 500);
        <?php endif; ?>
    </script>
</body>
</html>