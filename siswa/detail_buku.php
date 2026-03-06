<?php
/**
 * Halaman Detail Buku - Perpustakaan Digital Gamifikasi
 * SMK Mardi Yuana Cikembar
 */

require_once '../config/koneksi.php';
require_once '../config/auth.php';

$database = new Database();
$conn = $database->getConnection();

// Get book ID from URL
$id_buku = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_buku === 0) {
    header('Location: katalog.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$query_user = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get book details
$query = "SELECT b.*, 
          COALESCE(AVG(r.rating), 0) as avg_rating,
          COUNT(r.id_review) as total_review
          FROM buku b
          LEFT JOIN review_buku r ON b.id_buku = r.id_buku
          WHERE b.id_buku = ?
          GROUP BY b.id_buku";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();

if (!$book) {
    header('Location: katalog.php');
    exit();
}

// Get all reviews for this book
$query_reviews = "SELECT r.*, u.nama, u.avatar 
                  FROM review_buku r
                  JOIN users u ON r.id_user = u.id
                  WHERE r.id_buku = ?
                  ORDER BY r.created_at DESC";
$stmt = $conn->prepare($query_reviews);
$stmt->bind_param("i", $id_buku);
$stmt->execute();
$reviews = $stmt->get_result();

// Get user's review if exists
$query_user_review = "SELECT * FROM review_buku WHERE id_user = ? AND id_buku = ?";
$stmt = $conn->prepare($query_user_review);
$stmt->bind_param("ii", $user_id, $id_buku);
$stmt->execute();
$user_review = $stmt->get_result()->fetch_assoc();

// Check if user has borrowed this book
$query_borrowed = "SELECT * FROM peminjaman 
                   WHERE id_user = ? AND id_buku = ? AND status = 'dipinjam'";
$stmt = $conn->prepare($query_borrowed);
$stmt->bind_param("ii", $user_id, $id_buku);
$stmt->execute();
$is_borrowed = $stmt->get_result()->num_rows > 0;

// Check if user has returned this book (can review)
$query_returned = "SELECT * FROM peminjaman 
                   WHERE id_user = ? AND id_buku = ? AND status = 'kembali'";
$stmt = $conn->prepare($query_returned);
$stmt->bind_param("ii", $user_id, $id_buku);
$stmt->execute();
$can_review = $stmt->get_result()->num_rows > 0 && !$user_review;

// Get related books (same category)
$query_related = "SELECT * FROM buku 
                  WHERE kategori = ? AND id_buku != ? 
                  ORDER BY RAND() LIMIT 4";
$stmt = $conn->prepare($query_related);
$stmt->bind_param("si", $book['kategori'], $id_buku);
$stmt->execute();
$related_books = $stmt->get_result();

// Page title
$page_title = htmlspecialchars($book['judul']) . " - Detail Buku";
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
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
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
            overflow: hidden;
        }

        .bg-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(40px);
            animation: floatShape 8s ease-in-out infinite;
        }

        .bg-shape:nth-child(1) {
            width: 500px;
            height: 500px;
            top: -250px;
            right: -250px;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
        }

        .bg-shape:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -200px;
            left: -200px;
            background: linear-gradient(135deg, #48dbfb, #1dd1a1);
            animation-delay: 2s;
        }

        @keyframes floatShape {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-40px) scale(1.05); }
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
            margin: 40px auto;
            padding: 0 30px;
            position: relative;
            z-index: 1;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            flex-wrap: wrap;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .breadcrumb a:hover {
            color: #fbbf24;
        }

        .breadcrumb i {
            font-size: 12px;
        }

        /* Detail Card */
        .detail-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 40px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .book-detail-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
        }

        /* Book Cover */
        .book-cover-large {
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .book-cover-large img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .book-cover-large:hover img {
            transform: scale(1.05);
        }

        .stock-badge-large {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 2;
        }

        .stock-badge-large.available {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            box-shadow: 0 5px 15px rgba(72, 187, 120, 0.3);
        }

        .stock-badge-large.unavailable {
            background: linear-gradient(135deg, #f56565, #c53030);
            color: white;
            box-shadow: 0 5px 15px rgba(245, 101, 101, 0.3);
        }

        /* Book Info */
        .book-info-large {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .book-title-large {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            line-height: 1.3;
        }

        .book-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }

        .meta-item i {
            width: 35px;
            height: 35px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }

        /* Rating Large */
        .rating-large {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .rating-stars-large {
            display: flex;
            gap: 5px;
        }

        .rating-stars-large i {
            font-size: 24px;
            color: #fbbf24;
        }

        .rating-stars-large i.empty {
            color: #d1d5db;
        }

        .rating-score {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }

        .rating-count {
            color: #666;
            font-size: 14px;
        }

        /* Book Stats */
        .book-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            padding: 20px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
        }

        /* Book Description */
        .book-description-large {
            padding: 20px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        .description-content {
            color: #666;
            line-height: 1.8;
            font-size: 15px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-borrow-large {
            flex: 2;
            padding: 16px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-borrow-large:hover:not(.disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-borrow-large.disabled {
            background: linear-gradient(135deg, #a0aec0, #718096);
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-back-large {
            flex: 1;
            padding: 16px 30px;
            background: rgba(255, 255, 255, 0.1);
            color: #666;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-back-large:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        /* Reviews Section */
        .reviews-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .reviews-header h2 {
            font-size: 24px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-write-review {
            padding: 12px 25px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #1a1a2e;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-write-review:hover:not(.disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(251, 191, 36, 0.3);
        }

        .btn-write-review.disabled {
            background: linear-gradient(135deg, #a0aec0, #718096);
            opacity: 0.6;
            cursor: not-allowed;
            color: white;
        }

        .reviews-grid {
            display: grid;
            gap: 20px;
        }

        .review-item {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.3s;
        }

        .review-item.own-review {
            background: rgba(251, 191, 36, 0.1);
            border-left: 4px solid #fbbf24;
        }

        .review-item:hover {
            transform: translateX(5px);
        }

        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .reviewer-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .reviewer-details {
            flex: 1;
        }

        .reviewer-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .review-badge {
            font-size: 11px;
            background: #fbbf24;
            color: #1a1a2e;
            padding: 3px 10px;
            border-radius: 15px;
            font-weight: 600;
        }

        .review-date {
            font-size: 12px;
            color: #999;
        }

        .review-rating {
            display: flex;
            gap: 3px;
            margin-bottom: 10px;
        }

        .review-rating i {
            color: #fbbf24;
            font-size: 14px;
        }

        .review-comment {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
            margin-bottom: 15px;
            font-style: italic;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-edit,
        .btn-delete {
            background: none;
            border: none;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-edit {
            color: #667eea;
        }

        .btn-edit:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .btn-delete {
            color: #f56565;
        }

        .btn-delete:hover {
            background: rgba(245, 101, 101, 0.1);
        }

        .no-reviews {
            text-align: center;
            padding: 50px;
            color: #999;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 20px;
        }

        .no-reviews i {
            font-size: 50px;
            margin-bottom: 15px;
            color: #ddd;
        }

        /* Related Books */
        .related-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .related-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .related-header h2 {
            font-size: 24px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-view-all {
            color: #667eea;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-view-all:hover {
            transform: translateX(5px);
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .related-card {
            background: white;
            border-radius: 15px;
            padding: 15px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.15);
        }

        .related-cover {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .related-title {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .related-author {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .related-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }

        .related-rating i {
            color: #fbbf24;
            font-size: 12px;
        }

        .related-stock {
            font-size: 11px;
            color: #48bb78;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* Modal Review */
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
            transition: all 0.3s;
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
            transition: all 0.2s;
        }

        .rating-star:hover,
        .rating-star.selected {
            color: #fbbf24;
            transform: scale(1.1);
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
            transition: all 0.3s;
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
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .book-detail-grid {
                grid-template-columns: 1fr;
            }

            .book-cover-large {
                max-width: 300px;
                margin: 0 auto;
            }

            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 15px;
            }

            .main-container {
                padding: 20px;
            }

            .detail-card,
            .reviews-section,
            .related-section {
                padding: 25px;
            }

            .book-title-large {
                font-size: 28px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .book-meta {
                flex-direction: column;
                gap: 10px;
            }

            .reviews-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .related-grid {
                grid-template-columns: 1fr;
            }

            .rating-large {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
        }

        body.dark-mode .detail-card,
        body.dark-mode .reviews-section,
        body.dark-mode .related-section,
        body.dark-mode .navbar,
        body.dark-mode .modal-content {
            background: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .book-title-large,
        body.dark-mode .section-title,
        body.dark-mode .reviews-header h2,
        body.dark-mode .related-header h2,
        body.dark-mode .modal-title,
        body.dark-mode .reviewer-name {
            color: white;
        }

        body.dark-mode .meta-item,
        body.dark-mode .stat-label,
        body.dark-mode .rating-count,
        body.dark-mode .description-content,
        body.dark-mode .review-comment,
        body.dark-mode .review-date,
        body.dark-mode .related-author {
            color: #a0aec0;
        }

        body.dark-mode .btn-back-large {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        body.dark-mode .btn-back-large:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .review-item {
            background: rgba(255, 255, 255, 0.05);
        }

        body.dark-mode .review-item.own-review {
            background: rgba(251, 191, 36, 0.15);
        }

        body.dark-mode .related-card {
            background: rgba(255, 255, 255, 0.05);
        }

        body.dark-mode .related-title {
            color: white;
        }

        body.dark-mode .no-reviews {
            background: rgba(255, 255, 255, 0.02);
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="bg-shape"></div>
        <div class="bg-shape"></div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text" style="color: #333;">Memproses...</div>
    </div>

    <!-- Review Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeReviewModal()">&times;</span>
            <h2 class="modal-title" id="modalTitle">Tulis Ulasan</h2>
            
            <form method="POST" action="proses_review.php" id="reviewForm">
                <input type="hidden" name="id_buku" value="<?php echo $id_buku; ?>">
                <input type="hidden" name="rating" id="selectedRating" value="0">
                
                <div class="rating-selector" id="ratingSelector">
                    <i class="fas fa-star rating-star" data-rating="1"></i>
                    <i class="fas fa-star rating-star" data-rating="2"></i>
                    <i class="fas fa-star rating-star" data-rating="3"></i>
                    <i class="fas fa-star rating-star" data-rating="4"></i>
                    <i class="fas fa-star rating-star" data-rating="5"></i>
                </div>
                
                <div class="form-group">
                    <label for="komentar">Komentar <span style="color: #999; font-weight: normal;">(opsional)</span></label>
                    <textarea class="form-control" 
                              id="komentar" 
                              name="komentar" 
                              rows="4" 
                              placeholder="Tulis pengalaman Anda membaca buku ini..."><?php echo isset($user_review['komentar']) ? htmlspecialchars($user_review['komentar']) : ''; ?></textarea>
                </div>
                
                <button type="submit" name="submit_review" class="btn-submit">
                    <i class="fas fa-paper-plane"></i>
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
                <li><a href="../dashboard.php" class="nav-link"><i class="fas fa-home"></i>Dashboard</a></li>
                <li><a href="katalog.php" class="nav-link"><i class="fas fa-book"></i>Katalog</a></li>
                <li><a href="leaderboard.php" class="nav-link"><i class="fas fa-trophy"></i>Leaderboard</a></li>
                <li><a href="misi.php" class="nav-link"><i class="fas fa-tasks"></i>Misi</a></li>
                <li><a href="profil.php" class="nav-link"><i class="fas fa-user"></i>Profil</a></li>
                <li><a href="../logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
                <li><button id="theme-toggle" class="theme-toggle-btn"><i class="fas fa-moon"></i></button></li>
            </ul>
        </div>
    </nav>

    <main class="main-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <a href="katalog.php"><i class="fas fa-book"></i> Katalog</a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($book['judul']); ?></span>
        </div>

        <!-- Detail Card -->
        <div class="detail-card" data-aos="fade-up">
            <div class="book-detail-grid">
                <!-- Book Cover -->
                <div class="book-cover-large">
                    <img src="../assets/img/covers/<?php echo $book['cover'] ?: 'default-book.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($book['judul']); ?>">
                    <div class="stock-badge-large <?php echo $book['stok'] > 0 ? 'available' : 'unavailable'; ?>">
                        <i class="fas fa-<?php echo $book['stok'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo $book['stok'] > 0 ? 'Tersedia' : 'Stok Habis'; ?>
                    </div>
                </div>

                <!-- Book Info -->
                <div class="book-info-large">
                    <h1 class="book-title-large"><?php echo htmlspecialchars($book['judul']); ?></h1>
                    
                    <div class="book-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($book['penulis']); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <span><?php echo htmlspecialchars($book['kategori'] ?: '-'); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo $book['tahun_terbit'] ?: '-'; ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-barcode"></i>
                            <span><?php echo $book['isbn'] ?: '-'; ?></span>
                        </div>
                        <?php if ($book['penerbit']): ?>
                        <div class="meta-item">
                            <i class="fas fa-building"></i>
                            <span><?php echo htmlspecialchars($book['penerbit']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($book['lokasi_rak']): ?>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($book['lokasi_rak']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Rating -->
                    <div class="rating-large">
                        <div class="rating-stars-large">
                            <?php 
                            $avg_rating = round($book['avg_rating'], 1);
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= floor($avg_rating)):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i - 0.5 <= $avg_rating): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star empty"></i>
                            <?php endif; endfor; ?>
                        </div>
                        <div class="rating-score"><?php echo number_format($avg_rating, 1); ?></div>
                        <div class="rating-count">(<?php echo $book['total_review']; ?> ulasan)</div>
                    </div>

                    <!-- Book Stats -->
                    <div class="book-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $book['stok']; ?></div>
                            <div class="stat-label">Stok Tersedia</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $book['total_review']; ?></div>
                            <div class="stat-label">Jumlah Ulasan</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php 
                                $popularity = $book['total_review'] > 0 ? '🔥' : '📚';
                                echo $popularity;
                                ?>
                            </div>
                            <div class="stat-label">Popularitas</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if ($book['deskripsi']): ?>
                    <div class="book-description-large">
                        <h3 class="section-title">
                            <i class="fas fa-align-left"></i>
                            Deskripsi
                        </h3>
                        <div class="description-content">
                            <?php echo nl2br(htmlspecialchars($book['deskripsi'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($book['stok'] > 0): ?>
                            <?php if ($is_borrowed): ?>
                                <button class="btn-borrow-large disabled" disabled>
                                    <i class="fas fa-clock"></i>
                                    Sedang Dipinjam
                                </button>
                            <?php else: ?>
                                <a href="proses_pinjam.php?id=<?php echo $book['id_buku']; ?>" 
                                   class="btn-borrow-large"
                                   onclick="return confirmBorrow('<?php echo htmlspecialchars($book['judul']); ?>')">
                                    <i class="fas fa-book-open"></i>
                                    Pinjam Buku
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-borrow-large disabled" disabled>
                                <i class="fas fa-times-circle"></i>
                                Stok Habis
                            </button>
                        <?php endif; ?>
                        
                        <a href="katalog.php" class="btn-back-large">
                            <i class="fas fa-arrow-left"></i>
                            Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="reviews-section" data-aos="fade-up" data-aos-delay="100">
            <div class="reviews-header">
                <h2>
                    <i class="fas fa-comments"></i>
                    Ulasan Pembaca
                </h2>
                
                <?php if ($user_review): ?>
                    <button class="btn-write-review" onclick="editReview(<?php echo $user_review['rating']; ?>)">
                        <i class="fas fa-edit"></i>
                        Edit Ulasanmu
                    </button>
                <?php elseif ($can_review): ?>
                    <button class="btn-write-review" onclick="openReviewModal()">
                        <i class="fas fa-star"></i>
                        Tulis Ulasan
                    </button>
                <?php elseif ($is_borrowed): ?>
                    <button class="btn-write-review disabled" disabled data-tooltip="Kembalikan buku terlebih dahulu untuk memberi ulasan">
                        <i class="fas fa-lock"></i>
                        Ulasan Terkunci
                    </button>
                <?php elseif ($book['stok'] > 0): ?>
                    <button class="btn-write-review disabled" disabled data-tooltip="Pinjam dan baca buku ini terlebih dahulu">
                        <i class="fas fa-lock"></i>
                        Ulasan Terkunci
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($reviews->num_rows > 0): ?>
                <div class="reviews-grid">
                    <?php while($review = $reviews->fetch_assoc()): 
                        $is_own_review = ($review['id_user'] == $user_id);
                    ?>
                        <div class="review-item <?php echo $is_own_review ? 'own-review' : ''; ?>">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">
                                    <?php echo strtoupper(substr($review['nama'], 0, 1)); ?>
                                </div>
                                <div class="reviewer-details">
                                    <div class="reviewer-name">
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
                            <div class="review-actions">
                                <button class="btn-edit" onclick="editReview(<?php echo $review['rating']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="hapus_review.php?id=<?php echo $review['id_review']; ?>&buku=<?php echo $id_buku; ?>" 
                                   class="btn-delete"
                                   onclick="return confirmDeleteReview()">
                                    <i class="fas fa-trash"></i> Hapus
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-reviews">
                    <i class="far fa-comment-dots"></i>
                    <h3>Belum Ada Ulasan</h3>
                    <p>Jadilah yang pertama memberikan ulasan untuk buku ini!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Related Books -->
        <?php if ($related_books->num_rows > 0): ?>
        <div class="related-section" data-aos="fade-up" data-aos-delay="200">
            <div class="related-header">
                <h2>
                    <i class="fas fa-bookmark"></i>
                    Buku Terkait
                </h2>
                <a href="katalog.php?category=<?php echo urlencode($book['kategori']); ?>" class="btn-view-all">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="related-grid">
                <?php while($related = $related_books->fetch_assoc()): ?>
                    <a href="detail_buku.php?id=<?php echo $related['id_buku']; ?>" class="related-card">
                        <img src="../assets/img/covers/<?php echo $related['cover'] ?: 'default-book.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($related['judul']); ?>"
                             class="related-cover">
                        <h4 class="related-title"><?php echo htmlspecialchars($related['judul']); ?></h4>
                        <p class="related-author"><?php echo htmlspecialchars($related['penulis']); ?></p>
                        <div class="related-rating">
                            <?php 
                            $rating = $related['avg_rating'] ?? 0;
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= $rating):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; endfor; ?>
                            <span>(<?php echo $related['total_review'] ?? 0; ?>)</span>
                        </div>
                        <?php if ($related['stok'] > 0): ?>
                            <div class="related-stock">
                                <i class="fas fa-check-circle" style="color: #48bb78;"></i> Tersedia
                            </div>
                        <?php else: ?>
                            <div class="related-stock" style="color: #f56565;">
                                <i class="fas fa-times-circle"></i> Stok Habis
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Confirm borrow
        function confirmBorrow(bookTitle) {
            return confirm(`Apakah Anda yakin ingin meminjam buku "${bookTitle}"? Anda akan mendapatkan +20 XP!`);
        }

        // Confirm delete review
        function confirmDeleteReview() {
            return confirm('Apakah Anda yakin ingin menghapus ulasan ini?');
        }

        // Review Modal Functions
        const reviewModal = document.getElementById('reviewModal');
        const ratingStars = document.querySelectorAll('.rating-star');
        const selectedRating = document.getElementById('selectedRating');
        const komentarField = document.getElementById('komentar');
        const modalTitle = document.getElementById('modalTitle');

        function openReviewModal() {
            modalTitle.textContent = 'Tulis Ulasan';
            selectedRating.value = 0;
            komentarField.value = '';
            resetStars();
            reviewModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function editReview(rating) {
            modalTitle.textContent = 'Edit Ulasan';
            selectedRating.value = rating;
            highlightStars(rating);
            reviewModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeReviewModal() {
            reviewModal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Rating star selection
        ratingStars.forEach(star => {
            star.addEventListener('mouseenter', function() {
                const rating = this.dataset.rating;
                highlightStars(rating);
            });

            star.addEventListener('mouseleave', function() {
                const currentRating = selectedRating.value;
                if (currentRating > 0) {
                    highlightStars(currentRating);
                } else {
                    resetStars();
                }
            });

            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                selectedRating.value = rating;
                highlightStars(rating);
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
            const rating = selectedRating.value;
            if (rating == 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Rating Belum Dipilih',
                    text: 'Silakan pilih rating untuk buku ini',
                    confirmButtonColor: '#667eea'
                });
            }
        });

        // Close modal when clicking outside
        reviewModal.addEventListener('click', function(e) {
            if (e.target === reviewModal) {
                closeReviewModal();
            }
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', function() {
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
            setTimeout(() => this.style.transform = 'rotate(0)', 300);
        });

        // Check saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.querySelector('i').className = 'fas fa-sun';
        }

        // Loading overlay on navigation
        document.querySelectorAll('.nav-link, .btn-borrow-large, .btn-back-large').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    document.getElementById('loadingOverlay').classList.add('active');
                }
            });
        });

        // Parallax effect
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
    </script>
</body>
</html>